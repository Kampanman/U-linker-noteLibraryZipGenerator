<?php
session_start();

// ログインしていない場合はauth.phpへリダイレクト
if (!isset($_SESSION['user'])) {
    header('Location: auth.php');
    exit;
}

require_once '../server/properties.php';

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <?php echo $headLinks; ?>
</head>
<body>
    <div id="root"></div>

    <script src="https://unpkg.com/react@17/umd/react.development.js"></script>
    <script src="https://unpkg.com/react-dom@17/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script type="text/babel" src="../static/js/app.js"></script>
</body>
</html>
