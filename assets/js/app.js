/**
 * Excel Comment Analyzer - Frontend App
 * V1.0 - Pure JavaScript, no framework
 */

(function() {
    'use strict';

    // ==================== State ====================
    const state = {
        fileLoaded: false,
        currentTab: 'comments',
        currentPage: 1,
        perPage: 20,
        sortBy: 'index',
        sortOrder: 'asc',
        searchQuery: '',
        filters: {},
        hotLimit: 10,
        totalComments: 0,
        isFiltered: false,
    };

    // ==================== DOM References ====================
    const $ = (s) => document.querySelector(s);
    const $$ = (s) => document.querySelectorAll(s);

    const dom = {
        uploadSection: $('#uploadSection'),
        uploadZone: $('#uploadZone'),
        fileInput: $('#fileInput'),
        uploadProgress: $('#uploadProgress'),
        progressFill: $('#progressFill'),
        progressText: $('#progressText'),
        uploadStatus: $('#uploadStatus'),
        mainContent: $('#mainContent'),
        statsBar: $('#statsBar'),
        tabNav: $('#tabNav'),
        searchInput: $('#searchInput'),
        filterPanel: $('#filterPanel'),
        commentTableBody: $('#commentTableBody'),
        commentPagination: $('#commentPagination'),
        keywordCloud: $('#keywordCloud'),
        opinionTableBody: $('#opinionTableBody'),
        clusterGrid: $('#clusterGrid'),
        hotTableBody: $('#hotTableBody'),
        statsDetailGrid: $('#statsDetailGrid'),
        lengthChart: $('#lengthChart'),
        timeChart: $('#timeChart'),
        hourChart: $('#hourChart'),
        duplicateStatsDetail: $('#duplicateStatsDetail'),
        exportModal: $('#exportModal'),
        copyModal: $('#copyModal'),
        copyTextarea: $('#copyTextarea'),
        toast: $('#toast'),
    };

    // ==================== Upload ====================
    // Drag & drop
    dom.uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dom.uploadZone.classList.add('drag-over');
    });

    dom.uploadZone.addEventListener('dragleave', () => {
        dom.uploadZone.classList.remove('drag-over');
    });

    dom.uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dom.uploadZone.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) uploadFile(files[0]);
    });

    dom.fileInput.addEventListener('change', () => {
        if (dom.fileInput.files.length > 0) uploadFile(dom.fileInput.files[0]);
    });

    async function uploadFile(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['xlsx', 'xls', 'csv'].includes(ext)) {
            showToast('不支持的文件格式，仅支持 xlsx/xls/csv', 'error');
            return;
        }

        if (file.size > 50 * 1024 * 1024) {
            showToast('文件过大，最大支持 50MB', 'error');
            return;
        }

        // Show progress
        dom.uploadProgress.style.display = 'block';
        dom.uploadStatus.innerHTML = '';

        const formData = new FormData();
        formData.append('file', file);

        try {
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    dom.progressFill.style.width = pct + '%';
                    dom.progressText.textContent = '正在上传... ' + pct + '%';
                }
            });

            const uploadResult = await new Promise((resolve, reject) => {
                xhr.open('POST', 'api.php?action=upload');
                xhr.onload = () => {
                    try { resolve(JSON.parse(xhr.responseText)); }
                    catch { reject(new Error('响应解析失败')); }
                };
                xhr.onerror = () => reject(new Error('网络错误'));
                xhr.send(formData);
            });

            if (uploadResult.error) throw new Error(uploadResult.error);

            // Parse file
            dom.progressText.textContent = '正在解析...';
            dom.progressFill.style.width = '60%';

            const parseResult = await fetchJson('api.php?action=parse');
            if (parseResult.error) throw new Error(parseResult.error);

            dom.progressText.textContent = '正在分析...';
            dom.progressFill.style.width = '90%';

            // Small delay for UX
            await sleep(500);

            dom.progressFill.style.width = '100%';
            dom.progressText.textContent = '完成！共解析 ' + parseResult.total_rows + ' 行数据';

            await sleep(800);

            // Hide upload, show main content
            dom.uploadSection.style.display = 'none';
            dom.uploadProgress.style.display = 'none';
            dom.mainContent.style.display = 'block';
            state.fileLoaded = true;

            showToast('解析成功！共 ' + parseResult.total_rows + ' 条数据', 'success');

            // Load initial data
            loadComments();
            loadStats();

        } catch (err) {
            dom.uploadProgress.style.display = 'none';
            dom.uploadStatus.innerHTML = '<p class="error">' + err.message + '</p>';
            showToast(err.message, 'error');
        }
    }

    // ==================== Comments ====================
    async function loadComments() {
        if (!state.fileLoaded) return;

        let url;
        if (state.isFiltered) {
            url = 'api.php?action=filter&filters=' + encodeURIComponent(JSON.stringify(state.filters));
        } else if (state.searchQuery) {
            url = 'api.php?action=search&q=' + encodeURIComponent(state.searchQuery);
        } else {
            url = 'api.php?action=comments';
        }

        url += '&page=' + state.currentPage;
        url += '&per_page=' + state.perPage;
        url += '&sort=' + state.sortBy;
        url += '&order=' + state.sortOrder;

        try {
            const result = await fetchJson(url);
            if (result.error) throw new Error(result.error);

            state.totalComments = result.total;
            renderCommentTable(result.items);
            renderPagination(result, 'comments');
        } catch (err) {
            dom.commentTableBody.innerHTML = '<tr><td colspan="6" class="loading-cell">加载失败: ' + err.message + '</td></tr>';
        }
    }

    function renderCommentTable(items) {
        if (items.length === 0) {
            dom.commentTableBody.innerHTML = '<tr><td colspan="6" class="loading-cell">暂无评论数据</td></tr>';
            return;
        }

        dom.commentTableBody.innerHTML = items.map((c, i) => `
            <tr>
                <td>${c._index}</td>
                <td class="comment-content" title="${escapeHtml(c.content)}">${escapeHtml(c.content)}</td>
                <td class="likes-cell">${c.likes ? '&#10084; ' + c.likes : '-'}</td>
                <td class="time-cell">${c.time || '-'}</td>
                <td class="user-cell">${escapeHtml(c.username) || '-'}</td>
                <td class="action-cell">
                    <button class="btn-action" onclick="App.copyComment(${i})" title="复制评论">&#128203;</button>
                    <button class="btn-action" onclick="App.copyRow(${i})" title="复制整行">&#128278;</button>
                    <button class="btn-action" onclick="App.copyJson(${i})" title="复制JSON">JSON</button>
                </td>
            </tr>
        `).join('');

        // Store items for copy actions
        window._commentItems = items;
    }

    function renderPagination(result, type) {
        const target = type === 'comments' ? dom.commentPagination : null;
        if (!target) return;

        const { page, total_pages, total } = result;
        let html = '';

        html += `<button class="page-btn" ${page <= 1 ? 'disabled' : ''} onclick="App.goToPage(${page - 1})">上一页</button>`;

        // Page numbers
        const startPage = Math.max(1, page - 2);
        const endPage = Math.min(total_pages, page + 2);

        if (startPage > 1) {
            html += `<button class="page-btn" onclick="App.goToPage(1)">1</button>`;
            if (startPage > 2) html += `<span class="page-info">...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="App.goToPage(${i})">${i}</button>`;
        }

        if (endPage < total_pages) {
            if (endPage < total_pages - 1) html += `<span class="page-info">...</span>`;
            html += `<button class="page-btn" onclick="App.goToPage(${total_pages})">${total_pages}</button>`;
        }

        html += `<button class="page-btn" ${page >= total_pages ? 'disabled' : ''} onclick="App.goToPage(${page + 1})">下一页</button>`;
        html += `<span class="page-info">共 ${total} 条</span>`;

        target.innerHTML = html;
    }

    // ==================== Search ====================
    let searchTimer;
    dom.searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.searchQuery = dom.searchInput.value.trim();
            state.currentPage = 1;
            state.isFiltered = false;
            state.filters = {};
            loadComments();
        }, 300);
    });

    $('#btnSearch').addEventListener('click', () => {
        state.searchQuery = dom.searchInput.value.trim();
        state.currentPage = 1;
        state.isFiltered = false;
        state.filters = {};
        loadComments();
    });

    // ==================== Filter ====================
    $('#btnFilter').addEventListener('click', () => {
        dom.filterPanel.style.display = dom.filterPanel.style.display === 'none' ? 'block' : 'none';
    });

    $('#btnApplyFilter').addEventListener('click', () => {
        state.filters = {
            likes_min: $('#filterLikesMin').value,
            likes_max: $('#filterLikesMax').value,
            length_min: $('#filterLenMin').value,
            length_max: $('#filterLenMax').value,
            keyword: $('#filterKeyword').value,
            username: $('#filterUsername').value,
            time_from: $('#filterTimeFrom').value,
            time_to: $('#filterTimeTo').value,
        };

        // Remove empty filters
        Object.keys(state.filters).forEach(k => {
            if (state.filters[k] === '') delete state.filters[k];
        });

        state.currentPage = 1;
        state.isFiltered = Object.keys(state.filters).length > 0;
        state.searchQuery = '';
        dom.searchInput.value = '';
        loadComments();
        dom.filterPanel.style.display = 'none';
        showToast('筛选完成', 'success');
    });

    $('#btnResetFilter').addEventListener('click', () => {
        ['filterLikesMin', 'filterLikesMax', 'filterLenMin', 'filterLenMax', 'filterKeyword', 'filterUsername', 'filterTimeFrom', 'filterTimeTo'].forEach(id => {
            $('#' + id).value = '';
        });
        state.filters = {};
        state.isFiltered = false;
        state.currentPage = 1;
        loadComments();
        dom.filterPanel.style.display = 'none';
    });

    // ==================== Sort ====================
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('sortable')) {
            const sortBy = e.target.dataset.sort;
            if (state.sortBy === sortBy) {
                state.sortOrder = state.sortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                state.sortBy = sortBy;
                state.sortOrder = 'asc';
            }
            state.currentPage = 1;

            // Update sort indicators
            $$('.sortable').forEach(el => {
                el.classList.remove('asc', 'desc');
            });
            e.target.classList.add(state.sortOrder);

            loadComments();
        }
    });

    // ==================== Tabs ====================
    $$('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            switchTab(tab);
        });
    });

    function switchTab(tab) {
        state.currentTab = tab;

        $$('.tab-btn').forEach(b => b.classList.remove('active'));
        $$('.tab-pane').forEach(p => p.classList.remove('active'));

        const tabBtn = document.querySelector(`.tab-btn[data-tab="${tab}"]`);
        const tabPane = document.getElementById('tab-' + tab);
        if (tabBtn) tabBtn.classList.add('active');
        if (tabPane) tabPane.classList.add('active');

        // Show/hide toolbar
        const toolbar = $('#toolbar');
        toolbar.style.display = ['comments'].includes(tab) ? 'flex' : 'none';

        // Load tab content
        switch (tab) {
            case 'comments': loadComments(); break;
            case 'keywords': loadKeywords(); break;
            case 'opinions': loadOpinions(); break;
            case 'clusters': loadClusters(); break;
            case 'hot': loadHotComments(); break;
            case 'stats': loadDetailedStats(); break;
        }
    }

    // ==================== Keywords ====================
    async function loadKeywords() {
        dom.keywordCloud.innerHTML = '<p style="text-align:center;padding:40px">加载中...</p>';
        try {
            const keywords = await fetchJson('api.php?action=keywords&limit=100');
            if (keywords.error) throw new Error(keywords.error);
            renderKeywordCloud(keywords);
        } catch (err) {
            dom.keywordCloud.innerHTML = '<p style="text-align:center;color:red">加载失败: ' + err.message + '</p>';
        }
    }

    function renderKeywordCloud(keywords) {
        if (keywords.length === 0) {
            dom.keywordCloud.innerHTML = '<p style="text-align:center;padding:40px;color:#999">暂无关键词数据</p>';
            return;
        }

        const maxCount = keywords[0]?.count || 1;

        dom.keywordCloud.innerHTML = keywords.map(kw => {
            const size = 12 + (kw.count / maxCount) * 28;
            const alpha = 0.4 + (kw.count / maxCount) * 0.6;
            const hue = 230 - (kw.count / maxCount) * 200;
            const color = `hsla(${hue}, 70%, 50%, ${alpha})`;
            return `<span class="keyword-tag" style="font-size:${size}px;color:${color};border-color:${color}" 
                    onclick="App.searchKeyword('${escapeHtml(kw.word)}')" title="出现 ${kw.count} 次 - 点击查看相关评论">
                    ${escapeHtml(kw.word)}<span class="keyword-count">${kw.count}</span></span>`;
        }).join('');
    }

    // ==================== Opinions ====================
    async function loadOpinions() {
        dom.opinionTableBody.innerHTML = '<tr><td colspan="4" class="loading-cell">加载中...</td></tr>';
        try {
            const opinions = await fetchJson('api.php?action=opinions&limit=50');
            if (opinions.error) throw new Error(opinions.error);
            renderOpinions(opinions);
        } catch (err) {
            dom.opinionTableBody.innerHTML = '<tr><td colspan="4" class="loading-cell">加载失败</td></tr>';
        }
    }

    function renderOpinions(opinions) {
        if (opinions.length === 0) {
            dom.opinionTableBody.innerHTML = '<tr><td colspan="4" class="loading-cell">暂无高频观点</td></tr>';
            return;
        }
        dom.opinionTableBody.innerHTML = opinions.map((o, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${escapeHtml(o.text)}</td>
                <td><strong>${o.count}</strong> 次</td>
                <td>${o.total_likes} &#10084;</td>
            </tr>
        `).join('');
    }

    // ==================== Clusters ====================
    async function loadClusters() {
        dom.clusterGrid.innerHTML = '<p style="text-align:center;padding:40px;grid-column:1/-1">加载中...</p>';
        try {
            const clusters = await fetchJson('api.php?action=clusters');
            if (clusters.error) throw new Error(clusters.error);
            renderClusters(clusters);
        } catch (err) {
            dom.clusterGrid.innerHTML = '<p style="text-align:center;grid-column:1/-1">加载失败</p>';
        }
    }

    function renderClusters(clusters) {
        const colors = ['#4361ee', '#f72585', '#7209b7', '#3a0ca3', '#4cc9f0', '#2ec4b6', '#ff9f1c', '#e71d36', '#7209b7', '#560bad'];
        dom.clusterGrid.innerHTML = clusters.map((c, i) => {
            const sample = c.comments.slice(0, 2).map(x => x.content).join(' | ');
            return `
            <div class="cluster-card" style="border-left-color: ${colors[i % colors.length]}" onclick="App.viewClusterComments('${escapeHtml(c.name)}')">
                <div class="cluster-name">${c.name}</div>
                <div class="cluster-count">${c.count}</div>
                <div class="cluster-sample" title="${escapeHtml(sample)}">${escapeHtml(sample).substring(0, 60)}...</div>
            </div>`;
        }).join('');
    }

    function viewClusterComments(clusterName) {
        // Set filter to the cluster keywords and show in comments tab
        const clusterKeywords = {
            '广告类': '广告',
            '收费类': '收费',
            '会员类': '会员',
            '更新类': '更新',
            '功能类': '功能',
            '兼容类': '兼容',
            '体验类': '体验',
            '客服类': '客服',
            '竞品类': '对比',
            '内容类': '内容',
        };
        state.filters = { keyword: clusterKeywords[clusterName] || clusterName };
        state.isFiltered = true;
        state.currentPage = 1;
        switchTab('comments');
        loadComments();
        showToast('已筛选: ' + clusterName, 'success');
    }

    // ==================== Hot Comments ====================
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('hot-limit')) {
            $$('.hot-limit').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            state.hotLimit = parseInt(e.target.dataset.limit);
            loadHotComments();
        }
    });

    async function loadHotComments() {
        dom.hotTableBody.innerHTML = '<tr><td colspan="5" class="loading-cell">加载中...</td></tr>';
        try {
            const hot = await fetchJson('api.php?action=hot&limit=' + state.hotLimit);
            if (hot.error) throw new Error(hot.error);
            renderHotComments(hot);
        } catch (err) {
            dom.hotTableBody.innerHTML = '<tr><td colspan="5" class="loading-cell">加载失败</td></tr>';
        }
    }

    function renderHotComments(hot) {
        if (hot.length === 0) {
            dom.hotTableBody.innerHTML = '<tr><td colspan="5" class="loading-cell">暂无数据</td></tr>';
            return;
        }
        dom.hotTableBody.innerHTML = hot.map((c, i) => `
            <tr>
                <td><strong>#${i + 1}</strong></td>
                <td>${escapeHtml(c.content)}</td>
                <td class="likes-cell">${c.likes ? '&#10084; ' + c.likes : '-'}</td>
                <td class="time-cell">${c.time || '-'}</td>
                <td class="user-cell">${escapeHtml(c.username) || '-'}</td>
            </tr>
        `).join('');
    }

    // ==================== Stats ====================
    async function loadStats() {
        try {
            const stats = await fetchJson('api.php?action=stats');
            if (!stats.error) {
                $('#statTotal').textContent = stats.total || 0;
                $('#statAvgLikes').textContent = stats.avg_likes || 0;
                $('#statMaxLikes').textContent = stats.max_likes || 0;
                $('#statKeywords').textContent = stats.keyword_count || 0;
                $('#statDupRate').textContent = 
                    (stats.total > 0 ? Math.round((stats.duplicate_count / stats.total) * 100) : 0) + '%';
                $('#statAvgLen').textContent = stats.avg_length || 0;
            }
        } catch (err) { /* ignore */ }
    }

    async function loadDetailedStats() {
        try {
            const stats = await fetchJson('api.php?action=stats');
            const lenStats = await fetchJson('api.php?action=length_stats');
            const dupStats = await fetchJson('api.php?action=duplicates');

            // Stats cards
            dom.statsDetailGrid.innerHTML = `
                <div class="stat-card">
                    <div class="stat-number">${stats.total || 0}</div>
                    <div class="stat-title">评论总数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.avg_likes || 0}</div>
                    <div class="stat-title">平均点赞</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.max_likes || 0}</div>
                    <div class="stat-title">最高点赞</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.min_likes || 0}</div>
                    <div class="stat-title">最低点赞</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.keyword_count || 0}</div>
                    <div class="stat-title">关键词数量</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${dupStats.duplicate_comments || 0}</div>
                    <div class="stat-title">重复评论</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${lenStats.average || 0}</div>
                    <div class="stat-title">平均评论长度</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${dupStats.duplicate_rate || 0}%</div>
                    <div class="stat-title">重复率</div>
                </div>
            `;

            // Length distribution chart
            if (lenStats.distribution) {
                renderBarChart(dom.lengthChart, lenStats.distribution, '字');
            }

            // Time distribution chart
            if (stats.time_distribution) {
                const timeDist = stats.time_distribution;
                // Only show last 30 entries
                const entries = Object.entries(timeDist).slice(-30);
                const timeObj = {};
                entries.forEach(([k, v]) => { timeObj[k] = v; });
                renderBarChart(dom.timeChart, timeObj, '', true);
            }

            // Active hours chart
            if (stats.active_hours) {
                const hoursObj = {};
                stats.active_hours.forEach((v, i) => {
                    hoursObj[String(i).padStart(2, '0') + ':00'] = v;
                });
                renderBarChart(dom.hourChart, hoursObj, '', true);
            }

            // Duplicate details
            if (dupStats.duplicates && dupStats.duplicates.length > 0) {
                let dupHtml = '<h3 style="margin-bottom:12px">重复评论详情</h3>';
                dupHtml += '<div class="table-container"><table class="data-table"><thead><tr><th>#</th><th>评论内容</th><th>重复次数</th></tr></thead><tbody>';
                dupStats.duplicates.slice(0, 20).forEach((d, i) => {
                    dupHtml += `<tr><td>${i + 1}</td><td>${escapeHtml(d.text)}</td><td><strong>${d.count}</strong></td></tr>`;
                });
                dupHtml += '</tbody></table></div>';
                dom.duplicateStatsDetail.innerHTML = dupHtml;
            } else {
                dom.duplicateStatsDetail.innerHTML = '<p style="text-align:center;color:#999">未发现明显重复评论</p>';
            }

        } catch (err) {
            dom.statsDetailGrid.innerHTML = '<p>加载失败</p>';
        }
    }

    function renderBarChart(container, data, suffix = '', compact = false) {
        if (!data || Object.keys(data).length === 0) {
            container.innerHTML = '<p style="text-align:center;color:#999;padding:40px">暂无数据</p>';
            return;
        }

        const entries = Object.entries(data);
        const maxVal = Math.max(...entries.map(e => e[1]), 1);

        container.innerHTML = entries.map(([label, value]) => {
            const h = Math.max(2, (value / maxVal) * 180);
            const displayLabel = compact ? label.substring(label.length - 5) : label;
            return `
            <div class="bar-item">
                <div class="bar-value">${value}</div>
                <div class="bar-fill" style="height:${h}px" title="${label}: ${value}"></div>
                <div class="bar-label">${displayLabel}</div>
            </div>`;
        }).join('');
    }

    // ==================== Export ====================
    $('#btnExport').addEventListener('click', openExportModal);

    function openExportModal() {
        dom.exportModal.style.display = 'flex';
        $('#exportInfo').textContent = '将导出当前 ' + state.totalComments + ' 条评论';
    }

    function closeExportModal() {
        dom.exportModal.style.display = 'none';
    }

    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-export')) {
            const format = e.target.dataset.format;
            exportData(format);
        }
    });

    function exportData(format) {
        let url = 'api.php?action=export&format=' + format;

        if (state.isFiltered) {
            url += '&mode=filter&filters=' + encodeURIComponent(JSON.stringify(state.filters));
        } else if (state.searchQuery) {
            url += '&mode=search&q=' + encodeURIComponent(state.searchQuery);
        }

        // Trigger download
        const a = document.createElement('a');
        a.href = url;
        a.download = 'comments_export.' + (format === 'markdown' ? 'md' : format);
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        closeExportModal();
        showToast('导出成功', 'success');
    }

    // ==================== Copy ====================
    function openCopyModal(text) {
        dom.copyTextarea.value = text;
        dom.copyModal.style.display = 'flex';
    }

    function closeCopyModal() {
        dom.copyModal.style.display = 'none';
    }

    function doCopy() {
        const text = dom.copyTextarea.value;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('已复制到剪贴板', 'success');
                closeCopyModal();
            }).catch(() => {
                showToast('复制失败，请重试', 'error');
            });
        } else {
            // Fallback for older browsers
            dom.copyTextarea.select();
            try {
                document.execCommand('copy');
                showToast('已复制到剪贴板', 'success');
            } catch (e) {
                showToast('复制失败，请手动复制', 'error');
            }
            closeCopyModal();
        }
    }

    function copyComment(index) {
        const items = window._commentItems || [];
        if (items[index]) openCopyModal(items[index].content);
    }

    function copyRow(index) {
        const items = window._commentItems || [];
        if (!items[index]) return;
        const c = items[index];
        openCopyModal(`内容: ${c.content}\n点赞: ${c.likes}\n时间: ${c.time}\n用户: ${c.username}\n作品: ${c.title}\n平台: ${c.platform}`);
    }

    function copyJson(index) {
        const items = window._commentItems || [];
        if (items[index]) {
            const c = items[index];
            openCopyModal(JSON.stringify({
                评论内容: c.content,
                点赞数: c.likes,
                时间: c.time,
                用户名: c.username,
                作品标题: c.title,
                平台: c.platform
            }, null, 2));
        }
    }

    async function copyAll(format) {
        showToast('正在复制...', 'success');
        let url = 'api.php?action=copy_all&format=' + format;

        if (state.isFiltered) {
            url += '&mode=filter&filters=' + encodeURIComponent(JSON.stringify(state.filters));
        } else if (state.searchQuery) {
            url += '&mode=search&q=' + encodeURIComponent(state.searchQuery);
        }

        try {
            const result = await fetchJson(url);
            openCopyModal(result.content);
        } catch (err) {
            showToast('复制失败', 'error');
        }
    }

    function searchKeyword(keyword) {
        state.filters = { keyword: keyword };
        state.isFiltered = true;
        state.currentPage = 1;
        state.searchQuery = '';
        dom.searchInput.value = '';
        switchTab('comments');
        loadComments();
        showToast('已筛选关键词: ' + keyword, 'success');
    }

    // ==================== Modals ====================
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-overlay')) {
            dom.exportModal.style.display = 'none';
            dom.copyModal.style.display = 'none';
        }
    });

    // ==================== Reupload ====================
    $('#btnReupload').addEventListener('click', () => {
        state.fileLoaded = false;
        dom.mainContent.style.display = 'none';
        dom.uploadSection.style.display = 'block';
        dom.uploadStatus.innerHTML = '';
        dom.uploadProgress.style.display = 'none';
        state.currentPage = 1;
        state.searchQuery = '';
        state.filters = {};
        state.isFiltered = false;
        dom.searchInput.value = '';
    });

    // ==================== Utilities ====================
    async function fetchJson(url) {
        const resp = await fetch(url);
        return await resp.json();
    }

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'success') {
        const toast = dom.toast;
        toast.textContent = message;
        toast.className = 'toast ' + type + ' show';
        clearTimeout(toast._timeout);
        toast._timeout = setTimeout(() => {
            toast.classList.remove('show');
        }, 2500);
    }

    function goToPage(page) {
        state.currentPage = page;
        loadComments();
        document.querySelector('#tab-comments').scrollIntoView({ behavior: 'smooth' });
    }

    // ==================== Expose to Global ====================
    window.App = {
        copyComment,
        copyRow,
        copyJson,
        copyAll,
        searchKeyword,
        viewClusterComments,
        goToPage,
        doCopy,
        openCopyModal,
        closeCopyModal,
        closeExportModal,
    };

    // ==================== Init ====================
    console.log('Excel Comment Analyzer V1.0 Ready');
})();
