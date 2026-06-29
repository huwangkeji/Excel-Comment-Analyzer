<?php
/**
 * Excel Comment Analyzer - API Router
 * All AJAX endpoints handled here
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ==================== Upload ====================
        case 'upload':
            handleUpload();
            break;

        // ==================== Parse ====================
        case 'parse':
            handleParse();
            break;

        // ==================== Comments ====================
        case 'comments':
            handleComments();
            break;

        // ==================== Search ====================
        case 'search':
            handleSearch();
            break;

        // ==================== Filter ====================
        case 'filter':
            handleFilter();
            break;

        // ==================== Keywords ====================
        case 'keywords':
            handleKeywords();
            break;

        // ==================== Opinions ====================
        case 'opinions':
            handleOpinions();
            break;

        // ==================== Clusters ====================
        case 'clusters':
            handleClusters();
            break;

        // ==================== Hot Comments ====================
        case 'hot':
            handleHot();
            break;

        // ==================== Duplicates ====================
        case 'duplicates':
            handleDuplicates();
            break;

        // ==================== Length Stats ====================
        case 'length_stats':
            handleLengthStats();
            break;

        // ==================== Overall Stats ====================
        case 'stats':
            handleStats();
            break;

        // ==================== Export ====================
        case 'export':
            handleExport();
            break;

        // ==================== Copy ====================
        case 'copy_all':
            handleCopyAll();
            break;

        // ==================== Field Mapping ====================
        case 'fields':
            handleFields();
            break;

        default:
            jsonResponse(['error' => 'Unknown action: ' . $action], 400);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

// ==================== Handler Functions ====================

function getAnalyzer()
{
    if (!isset($_SESSION['analyzer_data'])) {
        throw new Exception('请先上传并解析Excel文件');
    }

    $data = $_SESSION['analyzer_data'];
    $analyzer = new CommentAnalyzer();
    $analyzer->loadData($data['headers'], $data['data']);
    return $analyzer;
}

function handleUpload()
{
    if (!isset($_FILES['file'])) {
        throw new Exception('未收到文件');
    }

    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => '文件超过服务器限制',
            UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件上传不完整',
            UPLOAD_ERR_NO_FILE => '未选择文件',
        ];
        throw new Exception($messages[$file['error']] ?? '上传错误');
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('文件过大，最大支持50MB');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        throw new Exception('不支持的文件格式，仅支持 xlsx/xls/csv');
    }

    // Generate unique filename
    $sessionId = session_id();
    $uniqueName = $sessionId . '_' . time() . '.' . $ext;
    $targetPath = UPLOAD_DIR . '/' . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('文件保存失败');
    }

    // Store in session
    $_SESSION['uploaded_file'] = $targetPath;
    $_SESSION['original_name'] = $file['name'];

    jsonResponse([
        'success' => true,
        'message' => '文件上传成功',
        'filename' => $file['name'],
        'size' => $file['size'],
    ]);
}

function handleParse()
{
    if (!isset($_SESSION['uploaded_file'])) {
        throw new Exception('请先上传文件');
    }

    $filePath = $_SESSION['uploaded_file'];
    if (!file_exists($filePath)) {
        throw new Exception('文件已过期，请重新上传');
    }

    $reader = new ExcelReader();
    $result = $reader->read($filePath);

    // Store parsed data in session
    $_SESSION['analyzer_data'] = $result;

    // Clean up upload
    @unlink($filePath);

    // Get field mapping
    $tmpAnalyzer = new CommentAnalyzer();
    $tmpAnalyzer->loadData($result['headers'], $result['data']);
    $mapping = $tmpAnalyzer->getFieldMapping();

    jsonResponse([
        'success' => true,
        'message' => '解析完成',
        'headers' => $result['headers'],
        'total_rows' => $result['total_rows'],
        'sheets' => $result['sheets'],
        'field_mapping' => $mapping,
    ]);
}

function handleComments()
{
    $analyzer = getAnalyzer();
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);
    $sort = $_GET['sort'] ?? 'index';
    $order = $_GET['order'] ?? 'asc';

    jsonResponse($analyzer->getComments($page, $perPage, $sort, $order));
}

function handleSearch()
{
    $analyzer = getAnalyzer();
    $query = $_GET['q'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);
    $sort = $_GET['sort'] ?? 'index';
    $order = $_GET['order'] ?? 'asc';

    jsonResponse($analyzer->searchComments($query, $page, $perPage, $sort, $order));
}

function handleFilter()
{
    $analyzer = getAnalyzer();
    $filters = json_decode($_GET['filters'] ?? '{}', true) ?: [];
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);
    $sort = $_GET['sort'] ?? 'index';
    $order = $_GET['order'] ?? 'asc';

    jsonResponse($analyzer->filterComments($filters, $page, $perPage, $sort, $order));
}

function handleKeywords()
{
    $analyzer = getAnalyzer();
    $limit = (int)($_GET['limit'] ?? 50);
    $keyword = $_GET['keyword'] ?? '';

    $keywords = $analyzer->getKeywordStats($limit);

    // Filter by keyword if specified
    if (!empty($keyword)) {
        $kw = mb_strtolower($keyword, 'UTF-8');
        $keywords = array_values(array_filter($keywords, function ($item) use ($kw) {
            return mb_strpos(mb_strtolower($item['word'], 'UTF-8'), $kw) !== false;
        }));
    }

    jsonResponse($keywords);
}

function handleOpinions()
{
    $analyzer = getAnalyzer();
    $limit = (int)($_GET['limit'] ?? 50);
    jsonResponse($analyzer->getOpinionStats($limit));
}

function handleClusters()
{
    $analyzer = getAnalyzer();
    jsonResponse($analyzer->getClusters());
}

function handleHot()
{
    $analyzer = getAnalyzer();
    $limit = (int)($_GET['limit'] ?? 10);
    jsonResponse($analyzer->getHotComments($limit));
}

function handleDuplicates()
{
    $analyzer = getAnalyzer();
    jsonResponse($analyzer->getDuplicateStats());
}

function handleLengthStats()
{
    $analyzer = getAnalyzer();
    jsonResponse($analyzer->getLengthStats());
}

function handleStats()
{
    $analyzer = getAnalyzer();
    jsonResponse($analyzer->getOverallStats());
}

function handleExport()
{
    $analyzer = getAnalyzer();
    $format = $_GET['format'] ?? 'json';

    // Get items to export
    $mode = $_GET['mode'] ?? 'all';

    switch ($mode) {
        case 'search':
            $query = $_GET['q'] ?? '';
            $result = $analyzer->searchComments($query, 1, 100000);
            break;
        case 'filter':
            $filters = json_decode($_GET['filters'] ?? '{}', true) ?: [];
            $result = $analyzer->filterComments($filters, 1, 100000);
            break;
        default:
            $result = $analyzer->getComments(1, 100000);
            break;
    }

    $items = $result['items'];
    $output = $analyzer->exportData($items, $format);

    $contentTypes = [
        'json' => 'application/json',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'markdown' => 'text/markdown',
    ];

    $extensions = [
        'json' => 'json',
        'csv' => 'csv',
        'txt' => 'txt',
        'markdown' => 'md',
    ];

    $ct = $contentTypes[$format] ?? 'application/octet-stream';
    $ext = $extensions[$format] ?? 'dat';

    header('Content-Type: ' . $ct . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="comments_export.' . $ext . '"');
    header('Content-Length: ' . strlen($output));
    echo $output;
    exit;
}

function handleCopyAll()
{
    $analyzer = getAnalyzer();
    $format = $_GET['format'] ?? 'json';

    // Get all comments or filtered
    $mode = $_GET['mode'] ?? 'all';
    switch ($mode) {
        case 'search':
            $query = $_GET['q'] ?? '';
            $result = $analyzer->searchComments($query, 1, 100000);
            break;
        case 'filter':
            $filters = json_decode($_GET['filters'] ?? '{}', true) ?: [];
            $result = $analyzer->filterComments($filters, 1, 100000);
            break;
        default:
            $result = $analyzer->getComments(1, 100000);
            break;
    }

    $items = $result['items'];
    $output = $analyzer->exportData($items, $format);

    jsonResponse(['content' => $output, 'format' => $format, 'count' => count($items)]);
}

function handleFields()
{
    $analyzer = getAnalyzer();
    jsonResponse(['mapping' => $analyzer->getFieldMapping()]);
}

// ==================== Helpers ====================

function jsonResponse($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
