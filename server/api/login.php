<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = '無効なリクエストです。';
    echo json_encode($response);
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $response['message'] = 'メールアドレスとパスワードを入力してください。';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $connection->prepare("SELECT * FROM ".ACCOUNT_TABLE." WHERE email = :email AND is_stopped = 0");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // ログイン成功
        $_SESSION['user'] = [
            'owner_id' => $user['owner_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'is_teacher' => $user['is_teacher'],
            'is_master' => $user['is_master']
        ];
        $response['success'] = true;
    } else {
        // ログイン失敗
        $response['message'] = 'ユーザーIDまたはパスワードが正しくありません。';
    }

} catch (PDOException $e) {
    $response['message'] = 'データベースエラー: ' . $e->getMessage();
}

echo json_encode($response);