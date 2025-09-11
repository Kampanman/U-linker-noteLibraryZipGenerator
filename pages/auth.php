<?php
session_start();

// 既にログインしている場合はindex.phpへリダイレクト
if (isset($_SESSION['user'])) {
    header('Location: index.php');
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
    <div class="login-container">
        <h2><?php echo CURRENT_CONTENTS_NAME.'<br/>ログインページ'; ?></h2>
        <div id="error-message" class="error-message"></div>
        <div class="login-form">
            <input type="email" id="email" placeholder="ユーザーID (メールアドレス)" required>
            <input type="password" id="password" placeholder="パスワード" required>
            <button id="login-btn">ログイン</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        document.getElementById('login-btn').addEventListener('click', function() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorMessage = document.getElementById('error-message');

            errorMessage.textContent = '';

            if (!email || !password) {
                errorMessage.textContent = 'ユーザーIDとパスワードを入力してください。';
                return;
            }

            const params = new URLSearchParams();
            params.append('email', email);
            params.append('password', password);

            axios.post('../server/api/login.php', params)
                .then(function(response) {
                    if (response.data.success) {
                        window.location.href = 'index.php';
                    } else {
                        errorMessage.textContent = response.data.message || 'ログインに失敗しました。';
                    }
                })
                .catch(function(error) {
                    errorMessage.textContent = 'エラーが発生しました。もう一度お試しください。';
                    console.error(error);
                });
        });
    </script>
</body>
</html>
