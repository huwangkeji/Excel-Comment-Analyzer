<?php
/**
 * CommentAnalyzer - Core analysis engine
 * All algorithms are pure PHP, no AI, no external APIs
 */

class CommentAnalyzer
{
    private $comments = [];
    private $fieldMapping = [];
    private $stopWords = [];

    // Common Chinese/English stop words
    private $defaultStopWords = [
        '的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都', '一', '一个',
        '上', '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看', '好',
        '自己', '这', '他', '她', '它', '们', '那', '些', '什么', '怎么', '如果', '因为',
        '所以', '但是', '可以', '这个', '那个', '还是', '只是', '的话', '而且', '虽然',
        '然后', '已经', '应该', '可能', '觉得', '知道', '真的', '比较', '非常',
        'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'can', 'shall', 'to', 'of', 'in', 'for',
        'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during',
        'it', 'its', 'this', 'that', 'these', 'those', 'i', 'me', 'my', 'we', 'our',
        'and', 'but', 'or', 'not', 'no', 'so', 'if', 'than', 'then', 'also',
        'just', 'very', 'too', 'really', 'only', 'about', 'more', 'some',
    ];

    public function __construct()
    {
        $this->stopWords = $this->defaultStopWords;
    }

    /**
     * Load parsed data into analyzer
     */
    public function loadData($headers, $data)
    {
        $this->fieldMapping = $this->autoDetectFields($headers);
        $this->comments = [];

        foreach ($data as $rowIdx => $row) {
            $comment = $this->mapRowToComment($row, $rowIdx);
            if ($comment && DataCleaner::isValidComment($comment['content'])) {
                $comment['content'] = DataCleaner::cleanComment($comment['content']);
                $comment['content_original'] = $comment['content'];
                $comment['likes'] = DataCleaner::cleanNumber($comment['likes']);
                $comment['time'] = DataCleaner::cleanDateTime($comment['time']);
                $this->comments[] = $comment;
            }
        }

        return count($this->comments);
    }

    /**
     * Auto-detect which column maps to which field
     */
    private function autoDetectFields($headers)
    {
        $mapping = [
            'content' => -1,
            'likes' => -1,
            'time' => -1,
            'username' => -1,
            'title' => -1,
            'platform' => -1,
        ];

        $keywords = [
            'content' => ['评论内容', '评论', '留言', '内容', '正文', 'comment', 'content', 'text', 'body', '回复', '评价'],
            'likes' => ['点赞数', '点赞量', '点赞', '赞数', '赞', 'like', 'likes', '喜欢', '顶', '支持', 'upvote', '热度'],
            'time' => ['评论时间', '发布时间', '创建时间', '时间', '日期', 'time', 'date', 'datetime'],
            'username' => ['用户名', '昵称', 'username', 'user name', 'user', 'author', '作者', 'name'],
            'title' => ['作品标题', '作品', '标题', 'title', 'subject', '视频', '帖子', '文章'],
            'platform' => ['平台', '来源', 'platform', 'source', '渠道', 'channel'],
        ];

        // Track match quality (longer match = better quality)
        $matchQuality = [];
        foreach ($mapping as $field => $v) $matchQuality[$field] = 0;

        foreach ($headers as $i => $header) {
            $headerLower = mb_strtolower(trim((string)$header), 'UTF-8');
            foreach ($keywords as $field => $patterns) {
                foreach ($patterns as $pattern) {
                    if (mb_strpos($headerLower, $pattern) !== false) {
                        $matchLen = mb_strlen($pattern, 'UTF-8');
                        // Only override if this is a better (longer) match
                        if ($matchLen > $matchQuality[$field]) {
                            $mapping[$field] = $i;
                            $matchQuality[$field] = $matchLen;
                        }
                        break;
                    }
                }
            }
        }

        // If content not found, use the first column
        if ($mapping['content'] < 0) {
            $mapping['content'] = 0;
        }

        // If time not found but we have 3+ columns, try to find date-like pattern
        if ($mapping['time'] < 0 && count($headers) > 2) {
            $mapping['time'] = 2; // Often the 3rd column
        }

        // If username not found, try the 4th column
        if ($mapping['username'] < 0 && count($headers) > 3) {
            $mapping['username'] = 3; // Often the 4th column
        }

        return $mapping;
    }

    /**
     * Map a data row to a comment structure
     */
    private function mapRowToComment($row, $rowIdx)
    {
        $comment = [
            '_index' => $rowIdx + 1,
            'content' => '',
            'likes' => 0,
            'time' => '',
            'username' => '',
            'title' => '',
            'platform' => '',
            '_raw' => $row,
        ];

        foreach ($this->fieldMapping as $field => $colIdx) {
            if ($colIdx >= 0 && isset($row[$colIdx])) {
                $comment[$field] = trim((string)$row[$colIdx]);
            }
        }

        // Guess content if mapping failed
        if (empty($comment['content']) && !empty($row)) {
            $comment['content'] = trim((string)($row[0] ?? ''));
        }

        return $comment;
    }

    // ==================== Core Queries ====================

    /**
     * Get all comments with pagination
     */
    public function getComments($page = 1, $perPage = 20, $sort = 'index', $order = 'asc')
    {
        $comments = $this->comments;

        // Sort
        usort($comments, function ($a, $b) use ($sort, $order) {
            $va = $a[$sort] ?? $a['_index'];
            $vb = $b[$sort] ?? $b['_index'];
            if ($va == $vb) return 0;
            $cmp = ($va < $vb) ? -1 : 1;
            return $order === 'desc' ? -$cmp : $cmp;
        });

        $total = count($comments);
        $totalPages = ceil($total / $perPage);
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        $items = array_slice($comments, $offset, $perPage);

        return [
            'items' => $items,
            'total' => $total,
            'page' => (int)$page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Search comments
     */
    public function searchComments($query, $page = 1, $perPage = 20, $sort = 'index', $order = 'asc')
    {
        if (empty($query)) {
            return $this->getComments($page, $perPage, $sort, $order);
        }

        $query = mb_strtolower($query, 'UTF-8');
        $results = [];

        foreach ($this->comments as $comment) {
            $searchText = mb_strtolower(
                $comment['content'] . ' ' . $comment['username'],
                'UTF-8'
            );
            if (mb_strpos($searchText, $query) !== false) {
                $results[] = $comment;
            }
        }

        // Sort
        usort($results, function ($a, $b) use ($sort, $order) {
            $va = $a[$sort] ?? $a['_index'];
            $vb = $b[$sort] ?? $b['_index'];
            if ($va == $vb) return 0;
            $cmp = ($va < $vb) ? -1 : 1;
            return $order === 'desc' ? -$cmp : $cmp;
        });

        $total = count($results);
        $totalPages = ceil($total / $perPage);
        $page = max(1, min($page, $totalPages > 0 ? $totalPages : 1));
        $offset = ($page - 1) * $perPage;
        $items = array_slice($results, $offset, $perPage);

        return [
            'items' => $items,
            'total' => $total,
            'page' => (int)$page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Filter comments by multiple conditions
     */
    public function filterComments($filters, $page = 1, $perPage = 20, $sort = 'index', $order = 'asc')
    {
        $results = $this->comments;

        // Filter by likes range
        if (isset($filters['likes_min']) && $filters['likes_min'] !== '') {
            $min = (int)$filters['likes_min'];
            $results = array_filter($results, function ($c) use ($min) {
                return $c['likes'] >= $min;
            });
        }
        if (isset($filters['likes_max']) && $filters['likes_max'] !== '') {
            $max = (int)$filters['likes_max'];
            $results = array_filter($results, function ($c) use ($max) {
                return $c['likes'] <= $max;
            });
        }

        // Filter by comment length
        if (isset($filters['length_min']) && $filters['length_min'] !== '') {
            $min = (int)$filters['length_min'];
            $results = array_filter($results, function ($c) use ($min) {
                return mb_strlen($c['content']) >= $min;
            });
        }
        if (isset($filters['length_max']) && $filters['length_max'] !== '') {
            $max = (int)$filters['length_max'];
            $results = array_filter($results, function ($c) use ($max) {
                return mb_strlen($c['content']) <= $max;
            });
        }

        // Filter by time range
        if (!empty($filters['time_from'])) {
            $from = $filters['time_from'];
            $results = array_filter($results, function ($c) use ($from) {
                return $c['time'] >= $from;
            });
        }
        if (!empty($filters['time_to'])) {
            $to = $filters['time_to'];
            $results = array_filter($results, function ($c) use ($to) {
                return $c['time'] <= $to;
            });
        }

        // Filter by keyword in content
        if (!empty($filters['keyword'])) {
            $kw = mb_strtolower($filters['keyword'], 'UTF-8');
            $results = array_filter($results, function ($c) use ($kw) {
                return mb_strpos(mb_strtolower($c['content'], 'UTF-8'), $kw) !== false;
            });
        }

        // Filter by username
        if (!empty($filters['username'])) {
            $un = mb_strtolower($filters['username'], 'UTF-8');
            $results = array_filter($results, function ($c) use ($un) {
                return mb_strpos(mb_strtolower($c['username'], 'UTF-8'), $un) !== false;
            });
        }

        // Filter by platform
        if (!empty($filters['platform'])) {
            $plat = mb_strtolower($filters['platform'], 'UTF-8');
            $results = array_filter($results, function ($c) use ($plat) {
                return mb_strpos(mb_strtolower($c['platform'], 'UTF-8'), $plat) !== false;
            });
        }

        $results = array_values($results);

        // Sort
        usort($results, function ($a, $b) use ($sort, $order) {
            $va = $a[$sort] ?? $a['_index'];
            $vb = $b[$sort] ?? $b['_index'];
            if ($va == $vb) return 0;
            $cmp = ($va < $vb) ? -1 : 1;
            return $order === 'desc' ? -$cmp : $cmp;
        });

        $total = count($results);
        $totalPages = $total > 0 ? ceil($total / $perPage) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        $items = array_slice($results, $offset, $perPage);

        return [
            'items' => $items,
            'total' => $total,
            'page' => (int)$page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    // ==================== Statistics ====================

    /**
     * High-frequency keyword analysis
     */
    public function getKeywordStats($limit = 50)
    {
        $wordCounts = [];

        foreach ($this->comments as $comment) {
            $text = $comment['content'];
            // Segment Chinese text by character combinations and extract words
            $words = $this->extractKeywords($text);
            foreach ($words as $word) {
                $word = mb_strtolower($word, 'UTF-8');
                if (!isset($wordCounts[$word])) {
                    $wordCounts[$word] = [
                        'word' => $word,
                        'count' => 0,
                        'comments' => []
                    ];
                }
                $wordCounts[$word]['count']++;
                $wordCounts[$word]['comments'][] = $comment;
            }
        }

        // Sort by count descending
        uasort($wordCounts, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        $result = array_slice(array_values($wordCounts), 0, $limit);

        // Limit comments per keyword to 20 samples
        foreach ($result as &$item) {
            $item['comments'] = array_slice($item['comments'], 0, 20);
        }

        return $result;
    }

    /**
     * Extract meaningful keywords from text
     */
    private function extractKeywords($text)
    {
        $keywords = [];

        // Remove URLs
        $text = preg_replace('/https?:\/\/\S+/', '', $text);

        // Remove date/time patterns
        $text = preg_replace('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/', ' ', $text);
        $text = preg_replace('/\d{1,2}:\d{2}(:\d{2})?/', ' ', $text);

        // Remove punctuations
        $text = preg_replace('/[\p{P}\p{S}]/u', ' ', $text);

        // Normalize spaces
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Split by spaces for mixed content
        $segments = explode(' ', $text);

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if (empty($segment)) continue;

            // Skip pure numbers and date-like tokens
            if (preg_match('/^\d+$/', $segment)) continue;
            if (preg_match('/^\d{4}$/', $segment)) continue; // year-like
            if (preg_match('/^\d{2}:\d{2}$/', $segment)) continue; // time-like

            // For Chinese text: extract 2-4 character n-grams
            if (preg_match('/[\x{4e00}-\x{9fff}]/u', $segment)) {
                $len = mb_strlen($segment, 'UTF-8');
                for ($n = 2; $n <= 4; $n++) {
                    for ($i = 0; $i <= $len - $n; $i++) {
                        $gram = mb_substr($segment, $i, $n, 'UTF-8');
                        $gram = trim($gram);
                        if (mb_strlen($gram, 'UTF-8') >= 2 && !$this->isStopWord($gram)) {
                            $keywords[] = $gram;
                        }
                    }
                }
                // Also extract single characters that are meaningful
                for ($i = 0; $i < $len; $i++) {
                    $char = mb_substr($segment, $i, 1, 'UTF-8');
                    if (preg_match('/^[\x{4e00}-\x{9fff}]$/u', $char) && !$this->isStopWord($char)) {
                        $keywords[] = $char;
                    }
                }
            } else {
                // For non-Chinese: use whole word
                $word = strtolower(trim($segment));
                // Skip pure numbers
                if (preg_match('/^\d+$/', $word)) continue;
                if (mb_strlen($word, 'UTF-8') >= 2 && !$this->isStopWord($word)) {
                    $keywords[] = $word;
                }
            }
        }

        // Deduplicate within same comment
        return array_unique($keywords);
    }

    /**
     * Check if word is a stop word
     */
    private function isStopWord($word)
    {
        return in_array(mb_strtolower($word, 'UTF-8'), $this->stopWords);
    }

    /**
     * High-frequency opinions (exact/similar comment matching)
     */
    public function getOpinionStats($limit = 50)
    {
        $opinionCounts = [];

        foreach ($this->comments as $comment) {
            $text = trim($comment['content']);
            if (mb_strlen($text, 'UTF-8') < 3) continue;

            $key = $this->normalizeText($text);

            if (!isset($opinionCounts[$key])) {
                $opinionCounts[$key] = [
                    'text' => $text,
                    'count' => 1,
                    'total_likes' => $comment['likes'],
                ];
            } else {
                $opinionCounts[$key]['count']++;
                $opinionCounts[$key]['total_likes'] += $comment['likes'];
                // Keep the longer version
                if (mb_strlen($text, 'UTF-8') > mb_strlen($opinionCounts[$key]['text'], 'UTF-8')) {
                    $opinionCounts[$key]['text'] = $text;
                }
            }
        }

        // Sort by count descending
        uasort($opinionCounts, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        return array_values(array_slice($opinionCounts, 0, $limit));
    }

    /**
     * Normalize text for similarity comparison
     */
    private function normalizeText($text)
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[\p{P}\p{S}\s]+/u', '', $text);
        return trim($text);
    }

    /**
     * Comment clustering by keyword rules
     */
    public function getClusters()
    {
        $clusterRules = [
            '广告类' => ['广告', '推广', '营销', 'ad', '推销', '宣传', '软文', '恰饭', '带货'],
            '收费类' => ['收费', '付费', '价格', '太贵', '贵了', '钱', '收费太高', '不免费', '花钱', '氪金', 'price', 'cost', 'VIP', '会员费'],
            '会员类' => ['会员', 'VIP', 'vip', '会员制', '开通会员', '充值', '订阅', 'subscribe'],
            '更新类' => ['更新', '升级', '新版', '版本', 'update', '新版本', '迭代', '发布'],
            '功能类' => ['功能', '建议', '需求', 'feature', '能不能', '希望', '增加', '添加', '没有', '缺少', '不支拀'],
            '兼容类' => ['兼容', '闪退', '崩溃', 'bug', 'Bug', 'BUG', '错误', '报错', '打不开', '卡顿', '卡死', 'crash', '出错', '不行', '不能用'],
            '体验类' => ['体验', '好用', '难用', '方便', '流畅', '慢', '快', '界面', 'UI', '设计', '丑', '美', '好看', '难看', '操作', '简洁', '复杂'],
            '客服类' => ['客服', '售后', '服务', '态度', '咨询', '投诉', '反馈', '联系', '回复'],
            '竞品类' => ['WPS', 'Office', 'Excel', 'Word', 'Google', '替代', '国产', '微软', '对比', '比较', '不如', '比'],
            '内容类' => ['教程', '视频', '文章', '内容', '学习', '有用', '干货', '帮助', '分享', '支持', '赞'],
        ];

        $clusters = [];
        foreach ($clusterRules as $name => $keywords) {
            $clusters[$name] = [
                'name' => $name,
                'keywords' => $keywords,
                'count' => 0,
                'comments' => []
            ];
        }

        // Add "其他" cluster
        $clusters['其他'] = [
            'name' => '其他',
            'keywords' => [],
            'count' => 0,
            'comments' => []
        ];

        foreach ($this->comments as $comment) {
            $matched = false;
            $text = mb_strtolower($comment['content'], 'UTF-8');

            foreach ($clusterRules as $name => $keywords) {
                foreach ($keywords as $kw) {
                    if (mb_strpos($text, mb_strtolower($kw, 'UTF-8')) !== false) {
                        $clusters[$name]['count']++;
                        $clusters[$name]['comments'][] = $comment;
                        $matched = true;
                        break 2;
                    }
                }
            }

            if (!$matched) {
                $clusters['其他']['count']++;
                $clusters['其他']['comments'][] = $comment;
            }
        }

        // Sort clusters by count
        uasort($clusters, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        // Limit comments per cluster
        foreach ($clusters as &$cluster) {
            $cluster['comments'] = array_slice($cluster['comments'], 0, 100);
        }

        return array_values($clusters);
    }

    /**
     * Hot comments ranking (by likes)
     */
    public function getHotComments($limit = 10)
    {
        $sorted = $this->comments;
        usort($sorted, function ($a, $b) {
            return $b['likes'] - $a['likes'];
        });

        return array_slice($sorted, 0, $limit);
    }

    /**
     * Duplicate comment detection
     */
    public function getDuplicateStats()
    {
        $normalized = [];
        $duplicates = [];
        $unique = [];

        foreach ($this->comments as $comment) {
            $key = $this->normalizeText($comment['content']);
            if (mb_strlen($key, 'UTF-8') < 2) continue;

            if (!isset($normalized[$key])) {
                $normalized[$key] = [
                    'text' => $comment['content'],
                    'count' => 1,
                    'comments' => [$comment],
                ];
            } else {
                $normalized[$key]['count']++;
                $normalized[$key]['comments'][] = $comment;
            }
        }

        foreach ($normalized as $key => $info) {
            if ($info['count'] > 1) {
                $duplicates[] = $info;
            } else {
                $unique[] = $info;
            }
        }

        usort($duplicates, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        $total = count($this->comments);
        $dupCount = 0;
        foreach ($duplicates as $d) {
            $dupCount += $d['count'];
        }

        return [
            'total_comments' => $total,
            'duplicate_comments' => $dupCount,
            'duplicate_rate' => $total > 0 ? round(($dupCount / $total) * 100, 2) : 0,
            'similar_groups' => count($duplicates),
            'duplicates' => array_slice($duplicates, 0, 50),
        ];
    }

    /**
     * Comment length statistics
     */
    public function getLengthStats()
    {
        if (empty($this->comments)) {
            return [
                'longest' => null,
                'shortest' => null,
                'average' => 0,
                'distribution' => []
            ];
        }

        $lengths = [];
        $longest = $this->comments[0];
        $shortest = $this->comments[0];
        $total = 0;

        foreach ($this->comments as $comment) {
            $len = mb_strlen($comment['content'], 'UTF-8');
            $lengths[] = $len;
            $total += $len;

            if ($len > mb_strlen($longest['content'], 'UTF-8')) {
                $longest = $comment;
            }
            if ($len < mb_strlen($shortest['content'], 'UTF-8')) {
                $shortest = $comment;
            }
        }

        $average = count($lengths) > 0 ? round($total / count($lengths), 1) : 0;

        // Distribution
        $distribution = [
            '1-10字' => 0, '11-30字' => 0, '31-50字' => 0,
            '51-100字' => 0, '101-200字' => 0, '200字以上' => 0
        ];

        foreach ($lengths as $len) {
            if ($len <= 10) $distribution['1-10字']++;
            elseif ($len <= 30) $distribution['11-30字']++;
            elseif ($len <= 50) $distribution['31-50字']++;
            elseif ($len <= 100) $distribution['51-100字']++;
            elseif ($len <= 200) $distribution['101-200字']++;
            else $distribution['200字以上']++;
        }

        return [
            'longest' => $longest,
            'shortest' => $shortest,
            'average' => $average,
            'distribution' => $distribution,
            'all_lengths' => $lengths,
        ];
    }

    /**
     * Overall data statistics
     */
    public function getOverallStats()
    {
        if (empty($this->comments)) {
            return [
                'total' => 0,
                'avg_likes' => 0,
                'max_likes' => 0,
                'min_likes' => 0,
                'time_distribution' => [],
                'active_hours' => [],
                'keyword_count' => 0,
                'duplicate_count' => 0,
                'avg_length' => 0,
                'platform_distribution' => [],
            ];
        }

        $likes = array_column($this->comments, 'likes');
        $avgLikes = count($likes) > 0 ? round(array_sum($likes) / count($likes), 2) : 0;
        $maxLikes = count($likes) > 0 ? max($likes) : 0;
        $minLikes = count($likes) > 0 ? min($likes) : 0;

        // Time distribution (by date)
        $timeDist = [];
        $activeHours = array_fill(0, 24, 0);
        foreach ($this->comments as $c) {
            if (!empty($c['time'])) {
                $date = substr($c['time'], 0, 10);
                if (!isset($timeDist[$date])) $timeDist[$date] = 0;
                $timeDist[$date]++;

                // Extract hour
                $ts = strtotime($c['time']);
                if ($ts) {
                    $hour = (int)date('H', $ts);
                    $activeHours[$hour]++;
                }
            }
        }
        ksort($timeDist);

        // Platform distribution
        $platDist = [];
        foreach ($this->comments as $c) {
            $p = $c['platform'] ?: '未知';
            if (!isset($platDist[$p])) $platDist[$p] = 0;
            $platDist[$p]++;
        }

        // Duplicate stats
        $dupStats = $this->getDuplicateStats();

        // Length stats
        $lenStats = $this->getLengthStats();

        // Keyword count
        $allWords = [];
        foreach ($this->comments as $c) {
            $words = $this->extractKeywords($c['content']);
            $allWords = array_merge($allWords, $words);
        }
        $uniqueKeywords = array_unique($allWords);

        return [
            'total' => count($this->comments),
            'avg_likes' => $avgLikes,
            'max_likes' => $maxLikes,
            'min_likes' => $minLikes,
            'time_distribution' => $timeDist,
            'active_hours' => $activeHours,
            'keyword_count' => count($uniqueKeywords),
            'duplicate_count' => $dupStats['duplicate_comments'],
            'avg_length' => $lenStats['average'],
            'platform_distribution' => $platDist,
        ];
    }

    /**
     * Export data in various formats
     */
    public function exportData($items, $format)
    {
        switch ($format) {
            case 'json':
                return $this->exportJson($items);
            case 'csv':
                return $this->exportCsv($items);
            case 'txt':
                return $this->exportTxt($items);
            case 'markdown':
                return $this->exportMarkdown($items);
            default:
                return $this->exportJson($items);
        }
    }

    private function exportJson($items)
    {
        $export = [];
        foreach ($items as $item) {
            $export[] = [
                '评论内容' => $item['content'],
                '点赞数' => $item['likes'],
                '时间' => $item['time'],
                '用户名' => $item['username'],
                '作品标题' => $item['title'],
                '平台' => $item['platform'],
            ];
        }
        return json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function exportCsv($items)
    {
        $output = "\xEF\xBB\xBF"; // BOM for Excel
        $output .= "评论内容,点赞数,时间,用户名,作品标题,平台\n";
        foreach ($items as $item) {
            $row = [
                str_replace('"', '""', $item['content']),
                $item['likes'],
                str_replace('"', '""', $item['time']),
                str_replace('"', '""', $item['username']),
                str_replace('"', '""', $item['title']),
                str_replace('"', '""', $item['platform']),
            ];
            $output .= '"' . implode('","', $row) . "\"\n";
        }
        return $output;
    }

    private function exportTxt($items)
    {
        $output = '';
        foreach ($items as $i => $item) {
            $output .= "--- 评论 #" . ($i + 1) . " ---\n";
            $output .= "内容: " . $item['content'] . "\n";
            $output .= "点赞: " . $item['likes'] . "\n";
            $output .= "时间: " . $item['time'] . "\n";
            $output .= "用户: " . $item['username'] . "\n";
            if ($item['title']) $output .= "作品: " . $item['title'] . "\n";
            if ($item['platform']) $output .= "平台: " . $item['platform'] . "\n";
            $output .= "\n";
        }
        return $output;
    }

    private function exportMarkdown($items)
    {
        $output = "# 评论分析导出\n\n";
        $output .= "| # | 评论内容 | 点赞 | 时间 | 用户 | 作品 | 平台 |\n";
        $output .= "|---|---------|------|------|------|------|------|\n";
        foreach ($items as $i => $item) {
            $content = mb_substr($item['content'], 0, 80) . (mb_strlen($item['content']) > 80 ? '...' : '');
            $output .= sprintf(
                "| %d | %s | %d | %s | %s | %s | %s |\n",
                $i + 1,
                str_replace('|', '\\|', $content),
                $item['likes'],
                $item['time'],
                $item['username'],
                $item['title'],
                $item['platform']
            );
        }
        return $output;
    }

    /**
     * Get field mapping info
     */
    public function getFieldMapping()
    {
        return $this->fieldMapping;
    }

    /**
     * Get all comments (for bulk operations)
     */
    public function getAllComments()
    {
        return $this->comments;
    }
}
