<?php
session_start();

require_once '../properties.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

if (!isset($_SESSION['user'])) {
    $response['message'] = '認証が必要です。';
    echo json_encode($response);
    exit;
}

// 1. ulinker_notesテーブルの固定エントリを追加
$response['data'][] = [
    'name' => 'ulinker_notes',
    'type' => 'db'
];

// 2. CSVファイルの一覧を取得
$csvDir = SERVERPATH.'/'.PARENT_CONTENTS_NAME.'/storage/csv';

if (!is_dir($csvDir)) {
    $response['success'] = true; // ディレクトリがなくてもエラーにはしない
    $response['message'] = 'CSVディレクトリが見つかりません。';
    echo json_encode($response);
    exit;
}

$files = scandir($csvDir);
foreach ($files as $file) {
    if (is_file($csvDir . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) == 'csv') {
        if (strpos($file, 'ulinker_notes') !== false) {
             $response['data'][] = [
                'name' => $file,
                'type' => 'csv'
            ];
        }
    }
}

$response['success'] = true;
echo json_encode($response);
