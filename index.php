<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel 评论分析器 - Comment Analyzer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="header-left">
                <h1><span class="icon">&#128202;</span> Excel 评论分析器</h1>
                <span class="version">V1.0</span>
            </div>
            <div class="header-right">
                <span class="badge badge-local">纯本地运行</span>
                <span class="badge badge-noai">无AI</span>
                <span class="badge badge-nodb">无数据库</span>
            </div>
        </header>

        <!-- Upload Area (initially visible) -->
        <section id="uploadSection" class="upload-section">
            <div class="upload-zone" id="uploadZone">
                <div class="upload-icon">&#128229;</div>
                <h2>上传 Excel 文件开始分析</h2>
                <p>支持 <strong>xlsx</strong> / <strong>xls</strong> / <strong>csv</strong> 格式，拖拽或点击上传</p>
                <p class="upload-hint">最大 50MB | 无需登录 | 数据不上传服务器</p>
                <input type="file" id="fileInput" accept=".xlsx,.xls,.csv" hidden>
                <button class="btn btn-primary btn-upload" onclick="document.getElementById('fileInput').click()">
                    选择文件
                </button>
            </div>
            <div class="upload-progress" id="uploadProgress" style="display:none">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <p id="progressText">正在上传...</p>
            </div>
            <div class="upload-status" id="uploadStatus"></div>
        </section>

        <!-- Main Content (hidden until file loaded) -->
        <section id="mainContent" class="main-content" style="display:none">

            <!-- Quick Stats Bar -->
            <div class="stats-bar" id="statsBar">
                <div class="stat-item">
                    <span class="stat-value" id="statTotal">0</span>
                    <span class="stat-label">评论总数</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="statAvgLikes">0</span>
                    <span class="stat-label">平均点赞</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="statMaxLikes">0</span>
                    <span class="stat-label">最高点赞</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="statKeywords">0</span>
                    <span class="stat-label">关键词数</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="statDupRate">0%</span>
                    <span class="stat-label">重复率</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="statAvgLen">0</span>
                    <span class="stat-label">平均长度</span>
                </div>
            </div>

            <!-- Tabs -->
            <nav class="tab-nav" id="tabNav">
                <button class="tab-btn active" data-tab="comments">评论列表</button>
                <button class="tab-btn" data-tab="keywords">关键词统计</button>
                <button class="tab-btn" data-tab="opinions">高频观点</button>
                <button class="tab-btn" data-tab="clusters">评论聚类</button>
                <button class="tab-btn" data-tab="hot">热门排行</button>
                <button class="tab-btn" data-tab="stats">数据统计</button>
            </nav>

            <!-- Toolbar -->
            <div class="toolbar" id="toolbar">
                <div class="toolbar-left">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="搜索评论内容或用户名..." autocomplete="off">
                        <button class="btn-search" id="btnSearch">&#128269;</button>
                    </div>
                    <button class="btn btn-sm" id="btnFilter">&#9881; 筛选</button>
                </div>
                <div class="toolbar-right">
                    <button class="btn btn-sm" id="btnExport">&#128190; 导出</button>
                    <button class="btn btn-sm btn-primary" id="btnReupload">&#128229; 重新上传</button>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="filter-panel" id="filterPanel" style="display:none">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>点赞数范围</label>
                        <div class="range-inputs">
                            <input type="number" id="filterLikesMin" placeholder="最小">
                            <span>~</span>
                            <input type="number" id="filterLikesMax" placeholder="最大">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>评论长度</label>
                        <div class="range-inputs">
                            <input type="number" id="filterLenMin" placeholder="最短字数">
                            <span>~</span>
                            <input type="number" id="filterLenMax" placeholder="最长字数">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>关键词</label>
                        <input type="text" id="filterKeyword" placeholder="搜索关键词">
                    </div>
                    <div class="filter-group">
                        <label>用户名</label>
                        <input type="text" id="filterUsername" placeholder="搜索用户名">
                    </div>
                    <div class="filter-group">
                        <label>时间范围</label>
                        <div class="range-inputs">
                            <input type="date" id="filterTimeFrom">
                            <span>~</span>
                            <input type="date" id="filterTimeTo">
                        </div>
                    </div>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-primary" id="btnApplyFilter">应用筛选</button>
                    <button class="btn" id="btnResetFilter">重置</button>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content">

                <!-- Comments List Tab -->
                <div class="tab-pane active" id="tab-comments">
                    <div class="table-container">
                        <table class="comment-table">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort="index">#</th>
                                    <th class="sortable" data-sort="content">评论内容</th>
                                    <th class="sortable" data-sort="likes">点赞</th>
                                    <th class="sortable" data-sort="time">时间</th>
                                    <th class="sortable" data-sort="username">用户</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="commentTableBody">
                                <tr><td colspan="6" class="loading-cell">加载中...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination" id="commentPagination"></div>
                </div>

                <!-- Keywords Tab -->
                <div class="tab-pane" id="tab-keywords">
                    <div class="keyword-cloud" id="keywordCloud"></div>
                </div>

                <!-- Opinions Tab -->
                <div class="tab-pane" id="tab-opinions">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr><th>#</th><th>观点内容</th><th>出现次数</th><th>总点赞</th></tr>
                            </thead>
                            <tbody id="opinionTableBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Clusters Tab -->
                <div class="tab-pane" id="tab-clusters">
                    <div class="cluster-grid" id="clusterGrid"></div>
                </div>

                <!-- Hot Comments Tab -->
                <div class="tab-pane" id="tab-hot">
                    <div class="hot-options">
                        <button class="btn btn-sm hot-limit active" data-limit="10">TOP 10</button>
                        <button class="btn btn-sm hot-limit" data-limit="50">TOP 50</button>
                        <button class="btn btn-sm hot-limit" data-limit="100">TOP 100</button>
                    </div>
                    <div class="table-container">
                        <table class="comment-table">
                            <thead>
                                <tr><th>排名</th><th>评论内容</th><th>点赞</th><th>时间</th><th>用户</th></tr>
                            </thead>
                            <tbody id="hotTableBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Stats Tab -->
                <div class="tab-pane" id="tab-stats">
                    <div class="stats-detail-grid" id="statsDetailGrid"></div>
                    <div class="stats-charts">
                        <div class="chart-container">
                            <h3>评论长度分布</h3>
                            <div class="bar-chart" id="lengthChart"></div>
                        </div>
                        <div class="chart-container">
                            <h3>评论时间分布</h3>
                            <div class="bar-chart" id="timeChart"></div>
                        </div>
                        <div class="chart-container">
                            <h3>活跃时间分布 (24小时)</h3>
                            <div class="bar-chart" id="hourChart"></div>
                        </div>
                    </div>
                    <div class="duplicate-stats" id="duplicateStatsDetail"></div>
                </div>
            </div>
        </section>

        <!-- Export Modal -->
        <div class="modal" id="exportModal" style="display:none">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <h3>导出评论数据</h3>
                <div class="export-options">
                    <button class="btn btn-export" data-format="json">JSON</button>
                    <button class="btn btn-export" data-format="csv">CSV (Excel)</button>
                    <button class="btn btn-export" data-format="txt">TXT 文本</button>
                    <button class="btn btn-export" data-format="markdown">Markdown</button>
                </div>
                <div class="export-info" id="exportInfo"></div>
                <button class="btn btn-close" onclick="App.closeExportModal()">关闭</button>
            </div>
        </div>

        <!-- Copy Modal -->
        <div class="modal" id="copyModal" style="display:none">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <h3>复制评论</h3>
                <textarea id="copyTextarea" readonly rows="10"></textarea>
                <div class="copy-actions">
                    <button class="btn btn-primary" onclick="App.doCopy()">复制到剪贴板</button>
                    <button class="btn" onclick="App.closeCopyModal()">关闭</button>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <div class="toast" id="toast"></div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
