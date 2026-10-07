<?php
/**
 * 美团到手价计算器 · 数据读写接口
 * 与 index.html 放在同一目录，配合 stores.json 使用。
 * 个人工具，无登录鉴权；仅保存本页面产生的店铺 JSON 数据。
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$file = __DIR__ . DIRECTORY_SEPARATOR . 'stores.json';
$action = isset($_GET['action']) ? $_GET['action'] : 'load';

if ($action === 'diag') {
    // 存储诊断：返回 PHP 版本、目录是否可写、stores.json 是否存在
    $tmp = $file . '.diag';
    $canWrite = @file_put_contents($tmp, '') !== false;
    if ($canWrite) @unlink($tmp);
    echo json_encode(array(
        'ok' => true,
        'php' => PHP_VERSION,
        'dirWritable' => $canWrite,
        'storesExists' => is_file($file)
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'load') {
    if (is_file($file)) {
        echo file_get_contents($file);
    } else {
        echo '[]';
    }
    exit;
}

if ($action === 'save') {
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) === 0) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'empty_body'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (strlen($raw) > 2 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(array('ok' => false, 'error' => 'too_large'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'not_array'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    // 原子写：先写临时文件再改名，避免写一半损坏数据
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $raw) === false) {
        http_response_code(500);
        echo json_encode(array('ok' => false, 'error' => 'write_failed', 'msg' => '目录不可写：请在 File Station 给本目录添加 http 用户写入权限'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        http_response_code(500);
        echo json_encode(array('ok' => false, 'error' => 'rename_failed', 'msg' => '目录不可写：请在 File Station 给本目录添加 http 用户写入权限'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(array('ok' => false, 'error' => 'unknown_action'), JSON_UNESCAPED_UNICODE);