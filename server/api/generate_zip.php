<?php
session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['message' => '認証が必要です。']);
    exit;
}
require_once '../db.php';
require_once '../properties.php';

$user = $_SESSION['user'];
$owner_id = $user['owner_id'];
$is_teacher = $user['is_teacher'];

$selected_csvs_json = $_POST['selected_csvs'] ?? '[]';
$selected_csvs = json_decode($selected_csvs_json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['message' => '無効なCSV選択データです。']);
    exit;
}

/**
 * CSVの行データを指定の要件に合わせてフォーマットする関数
 * @param array $rowData フォーマットする行データ（連想配列）
 * @return array フォーマット済みの行データ
 */
function formatCsvRow(array $rowData): array
{
    $formattedRow = [];
    foreach ($rowData as $key => $value) {
        switch ($key) {
            case 'url':
            case 'url_sub':
            case 'relate_notes':
            case 'relate_video_urls':
                // 空文字やnullの場合は'NULL'文字列を、それ以外はダブルクォーテーションで囲む
                $formattedRow[$key] = ($value === '' || $value === null || $value === 'NULL') ? 'NULL' : '"' . $value . '"';
                break;
            case 'note':
                // 改行コードを文字列 '\n' に変換し、ダブルクォーテーションで囲む
                $note_val = str_replace(["\r\n", "\r", "\n"], '\n', $value);
                $formattedRow[$key] = '"' . $note_val . '"';
                break;
            default:
                // その他のフィールドはダブルクォーテーションで囲む
                $formattedRow[$key] = '"' . $value . '"';
                break;
        }
    }
    return $formattedRow;
}

// 1. 一時ディレクトリを作成
$tempDir = SERVERPATH.'/'.PARENT_CONTENTS_NAME.'/storage/notes-csvs__' . $owner_id;
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// --- ulinker_notesテーブルからのエクスポート ---
try {
    $sql = "SELECT contents_id, title, "
            ."CASE WHEN url = '' THEN '\"\"' ELSE url END AS url, "
            ."url_sub, REPLACE(REPLACE(note, '\r', ''), '\n', '\\n') AS note, publicity, relate_notes, "
            ."REPLACE(REPLACE(relate_video_urls, '\r', ''), '\n', '\\n') AS relate_video_urls, "
            ."created_at, updated_at, created_user_id FROM ".NOTE_TABLE;
    
    $whereClauses = ["(created_user_id = :owner_id OR publicity = 1)"];
    if ($is_teacher == 1) {
        $whereClauses[] = "publicity = 2";
    }
    $sql .= " WHERE " . implode(' OR ', $whereClauses);

    $stmt = $connection->prepare($sql);
    $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
    $stmt->execute();

    $ulinkerNotesCsvPath = $tempDir . '/ulinker_notes__excerptedFromDb_' . $owner_id . '.csv';
    $fp = fopen($ulinkerNotesCsvPath, 'w');
    // ヘッダーを書き込み
    fputcsv($fp, ['contents_id', 'title', 'url', 'url_sub', 'note', 'publicity', 'relate_notes', 'relate_video_urls', 'created_at', 'updated_at', 'created_user_id']);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // 行データをフォーマット
        $formattedRow = formatCsvRow($row);
        fwrite($fp, implode(',', $formattedRow) . "\n");
    }
    fclose($fp);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'DBエラー: ' . $e->getMessage()]);
    exit;
}

// --- 選択されたCSVの処理 ---
$csvBaseDir = SERVERPATH.'/'.PARENT_CONTENTS_NAME.'/storage/csv';
foreach ($selected_csvs as $csvFile) {
    $sourceCsvPath = $csvBaseDir . '/' . $csvFile;
    if (file_exists($sourceCsvPath)) {
        $destCsvPath = $tempDir . '/' . pathinfo($csvFile, PATHINFO_FILENAME) . '__excerptedFromStorage_' . $owner_id . '.csv';
        $sourceFp = fopen($sourceCsvPath, 'r');
        $destFp = fopen($destCsvPath, 'w');

        $header = fgetcsv($sourceFp);
        if ($header) {
            fputcsv($destFp, $header); // ヘッダーをそのまま書き込む（フォーマットはしない）
        } else {
            continue; // 空のCSVはスキップ
        }

        while ($row = fgetcsv($sourceFp)) {
            $rowData = array_combine($header, $row);
            $publicity = $rowData['publicity'] ?? null;
            $createdUserId = $rowData['created_user_id'] ?? null;

            $shouldInclude = false;
            if ($createdUserId == $owner_id) $shouldInclude = true;
            if ($publicity == 1) $shouldInclude = true;
            if ($is_teacher == 1 && $publicity == 2) $shouldInclude = true;

            if ($shouldInclude) {
                // 行データをフォーマットして書き込む
                $formattedRow = formatCsvRow($rowData);
                fwrite($destFp, implode(',', $formattedRow) . "\n");
            }
        }
        fclose($sourceFp);
        fclose($destFp);
    }
}

// --- Zip化 ---
$zipPath = $tempDir . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($tempDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Zipファイルの作成に失敗しました。']);
    exit;
}

// --- ダウンロード処理 ---
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
header('Content-Length: ' . filesize($zipPath));
header('Pragma: no-cache');
header('Expires: 0');
readfile($zipPath);

// 一時ディレクトリ内のファイルを削除
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($files as $fileinfo) {
    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
    $todo($fileinfo->getRealPath());
}
rmdir($tempDir);
unlink($zipPath); // Zipファイルを削除

exit;