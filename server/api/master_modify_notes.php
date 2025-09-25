<?php
session_start();

// --- デバッグ用エラーログ設定 ---
ini_set('display_errors', 0); // 画面にエラーを表示しない
ini_set('log_errors', 1); // エラーログを有効にする
ini_set('error_log', dirname(__FILE__) . '/php_errors.log'); // エラーログファイルのパス
error_reporting(E_ALL);

require_once '../properties.php';
require_once '../db.php';

// --- ヘルパー関数 ---

// エラーレスポンスを生成して終了する
function send_error($message) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// ディレクトリを再帰的に削除する
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                    rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                else
                    unlink($dir. DIRECTORY_SEPARATOR .$object);
            }
        }
        rmdir($dir);
    }
}

// relate_notesの特殊文字をデコードする
function decode_relate_notes($str) {
    $str = str_replace('&quot;', '"', $str);
    $str = str_replace('&com;', ',', $str);
    $str = str_replace('&nbsp;', ' ', $str);
    return $str;
}

// relate_notesの特殊文字をエンコードする
function encode_relate_notes($str) {
    $str = str_replace('"', '&quot;', $str);
    $str = str_replace(',', '&com;', $str);
    return $str;
}

// title, noteカラムの値をエスケープする
function escape_special_chars($str) {
    if ($str === null || $str === '') {
        return $str;
    }
    // 既存のHTMLエンティティをデコードしてから全体をエンコードする
    // 既にエスケープされているものは二重にしない
    $decoded_str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    // 追加のカスタムエスケープ（例: 半角スペース）
    $escaped_str = htmlspecialchars($decoded_str, ENT_QUOTES, 'UTF-8');
    return $escaped_str;
}

// --- 共通の行処理関数 ---
function process_row($row, $all_contents_ids) {
    // title, noteカラムのエスケープ
    if (isset($row['title'])) {
        // 半角スペースを &nbsp; に変換
        $row['title'] = str_replace(' ', '&nbsp;', $row['title']);
    }
    if (isset($row['note'])) {
        // noteは &nbsp; 変換の対象外とする
        $row['note'] = str_replace(["\r\n", "\n", "\r"], '\n', $row['note']);
    }
    if (isset($row['relate_video_urls'])) {
        $row['relate_video_urls'] = str_replace(["\r\n", "\n", "\r"], '\n', $row['relate_video_urls']);
    }

    // relate_notesの整合性チェックと更新
    if (isset($row['relate_notes']) && $row['relate_notes'] !== 'NULL' && !empty($row['relate_notes'])) {
        $decoded_string = decode_relate_notes($row['relate_notes']);
        $related_array = json_decode($decoded_string, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($related_array)) {
            $valid_related_notes = [];
            foreach ($related_array as $related_item) {
                if (isset($related_item['contentsId']) && isset($all_contents_ids[$related_item['contentsId']])) {
                    // 存在する場合、titleを最新のものに更新
                    $related_item['title'] = $all_contents_ids[$related_item['contentsId']];
                    $valid_related_notes[] = $related_item;
                }
            }
            $re_encoded_string = json_encode($valid_related_notes, JSON_UNESCAPED_UNICODE);
            $row['relate_notes'] = encode_relate_notes($re_encoded_string);
        }
    }

    return $row;
}

// 行データをCSV形式の文字列に変換する
function build_csv_line(array $row): string
{
    $formatted_values = [];
    foreach ($row as $value) {
        if ($value === null || $value === 'NULL') {
            $formatted_values[] = 'NULL';
        } elseif (is_int($value) || is_float($value)) {
            // 整数と浮動小数点数のみを数値として扱い、クォートしない
            $formatted_values[] = $value;
        } else {
            $formatted_values[] = '"' . str_replace('"', '""', $value) . '"';
        }
    }
    return implode(',', $formatted_values) . "\n";
}

// --- メイン処理 ---

// 1. 権限チェック
if (!isset($_SESSION['user']['is_master']) || $_SESSION['user']['is_master'] != 1) {
    send_error('アクセス権限がありません。');
}

// 2. 選択されたCSVファイルリストを取得
$selected_csvs_json = $_POST['selected_csvs'] ?? '[]';
$selected_csvs = json_decode($selected_csvs_json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('無効なCSV選択データです。');
}


$all_contents_ids = [];
$temp_dir_path = null;
$zip_file_name = null;

try {
    // --- 準備: 一時ディレクトリ作成 ---
    $temp_dir_name = 'ulinker_notes__master_modified';
    $temp_dir_path = sys_get_temp_dir() . '/' . $temp_dir_name;
    if (file_exists($temp_dir_path)) {
        rrmdir($temp_dir_path);
    }
    mkdir($temp_dir_path, 0777, true);

    // --- 1段階目: 全てのcontents_idとtitleのマップを作成 ---
    // DBから
    $stmt = $connection->query("SELECT contents_id, title FROM " . NOTE_TABLE);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $all_contents_ids[$row['contents_id']] = $row['title'];
    }

    // CSVファイルから
    $csvDir = SERVERPATH . '/' . PARENT_CONTENTS_NAME . '/storage/csv';
    $all_csv_files = is_dir($csvDir) ? glob($csvDir . '/ulinker_notes*.csv') : [];
    $target_csv_files = [];
    foreach ($all_csv_files as $file_path) {
        if (in_array(basename($file_path), $selected_csvs)) {
            $target_csv_files[] = $file_path;
        }
        $file = $file_path; // $file変数をループ内で維持
        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle);
            if (!$header) continue;
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]); // BOM除去
            $id_idx = array_search('contents_id', $header);
            $title_idx = array_search('title', $header);

            if ($id_idx !== false && $title_idx !== false) {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (isset($data[$id_idx])) {
                        $all_contents_ids[$data[$id_idx]] = $data[$title_idx];
                    }
                }
            }
            fclose($handle);
        }
    }

    // --- 2段階目: 各データソースを処理し、CSVファイルに書き出す ---

    // DBの処理
    $db_output_path = $temp_dir_path . '/ulinker_notes_latest.csv';
    $db_output_handle = fopen($db_output_path, 'w');
    $stmt = $connection->query("SELECT * FROM " . NOTE_TABLE);
    $is_first_row = true;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($is_first_row) {
            // ヘッダー行を書き込み
            fwrite($db_output_handle, build_csv_line(array_keys($row)));
            $is_first_row = false;
        }
        $processed_row = process_row($row, $all_contents_ids);
        // データ行を書き込み
        fwrite($db_output_handle, build_csv_line($processed_row));
    }
    fclose($db_output_handle);

    // CSVファイルの処理
    foreach ($target_csv_files as $file) {
        $input_handle = fopen($file, "r");
        if (!$input_handle) continue;

        $output_path = $temp_dir_path . '/' . basename($file);
        $output_handle = fopen($output_path, 'w');

        $header = fgetcsv($input_handle);
        if (!$header) {
            fclose($input_handle);
            fclose($output_handle);
            continue;
        }
        $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]); // BOM除去
        fwrite($output_handle, build_csv_line($header));

        while (($data = fgetcsv($input_handle)) !== FALSE) {
            if (count($header) != count($data)) continue; // 不正な行はスキップ
            $row = array_combine($header, $data);
            $processed_row = process_row($row, $all_contents_ids);
            fwrite($output_handle, build_csv_line($processed_row));
        }
        fclose($input_handle);
        fclose($output_handle);
    }

    // --- Zipファイルの生成とダウンロード ---
    $zip_file_name = sys_get_temp_dir() . '/' . $temp_dir_name . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zip_file_name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        send_error("Zipファイルのオープンに失敗しました。");
    }

    // 一時ディレクトリの絶対パスを正規化
    $real_temp_dir_path = realpath($temp_dir_path);

    $files_to_zip = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temp_dir_path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files_to_zip as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($real_temp_dir_path) + 1);
        $zip->addFile($filePath, $relativePath);
    }
    $zip->close();

    // ダウンロード応答
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zip_file_name) . '"');
    header('Content-Length: ' . filesize($zip_file_name));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($zip_file_name);

    // --- 後処理 ---
    unlink($zip_file_name);
    rrmdir($temp_dir_path);

} catch (Exception $e) {
    // 念のため一時ファイルが残っていれば削除
    if ($zip_file_name && file_exists($zip_file_name)) {
        unlink($zip_file_name);
    }
    if ($temp_dir_path && file_exists($temp_dir_path)) {
        rrmdir($temp_dir_path);
    }
    send_error("処理中にエラーが発生しました: " . $e->getMessage());
}

exit;
