<?php
session_start();

// セッション変数を全て解除する
$_SESSION = array();

// セッションクッキーを削除する
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを破壊する
session_destroy();

header('Location: ../../pages/auth.php');
exit;
