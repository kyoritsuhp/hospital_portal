<?php
// ファイル名称: change_password.php
// 更新日時: 2025-10-08 (jinji連携改修、パスワード変更機能再実装)

require_once 'config.php';

// ログインチェック
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$current_user = getCurrentUser();

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // バリデーション
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'すべての項目を入力してください。';
    } elseif ($new_password !== $confirm_password) {
        $error = '新しいパスワードと確認用パスワードが一致しません。';
    } elseif (strlen($new_password) < 4) { // 簡単な文字数チェック
        $error = '新しいパスワードは4文字以上で入力してください。';
    } else {
        try {
            // jinjiデータベースに接続
            $jinji_pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=jinji;charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            // 現在のパスワードをjinji.staffテーブルから取得して確認
            $stmt_jinji = $jinji_pdo->prepare("SELECT staff_password FROM staff WHERE staff_id = ?");
            $stmt_jinji->execute([$current_user['user_id']]);
            $staff = $stmt_jinji->fetch();

            if ($staff && $staff['staff_password'] === $current_password) {
                // --- パスワードが正しい場合、更新処理 ---

                // トランザクション開始 (jinji と hospital_portal 両方を更新するため)
                $jinji_pdo->beginTransaction();
                $pdo->beginTransaction();

                try {
                    // 1. jinji.staffテーブルのパスワードを更新
                    $stmt_update_jinji = $jinji_pdo->prepare(
                        "UPDATE staff SET staff_password = ? WHERE staff_id = ?"
                    );
                    $stmt_update_jinji->execute([$new_password, $current_user['user_id']]);

                    // 2. hospital_portal.usersテーブルのパスワードも同期
                    $stmt_update_portal = $pdo->prepare(
                        "UPDATE users SET password = ? WHERE user_id = ?"
                    );
                    $stmt_update_portal->execute([$new_password, $current_user['user_id']]);

                    // 両方の更新が成功したらコミット
                    $jinji_pdo->commit();
                    $pdo->commit();

                    $success = 'パスワードを正常に変更しました。';

                } catch (Exception $e) {
                    // エラーが発生したらロールバック
                    $jinji_pdo->rollBack();
                    $pdo->rollBack();
                    throw $e; // エラーを再スローして外側のcatchで捕捉
                }
                
            } else {
                $error = '現在のパスワードが正しくありません。';
            }

        } catch (PDOException $e) {
            $error = 'データベースエラーにより、パスワードの変更に失敗しました。';
            error_log("Password change error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワード変更 - 協立病院ポータル</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .password-container { max-width: 480px; margin: 50px auto; padding: 0 20px; }
        .password-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .password-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e9ecef; }
        .password-header i { font-size: 48px; color: #667eea; margin-bottom: 15px; }
        .password-header h2 { color: #495057; font-size: 14px; margin-bottom: 10px; }
        .user-info { background: rgba(102, 126, 234, 0.1); padding: 12px; border-radius: 8px; text-align: center; font-size: 14px; color: #495057; margin-bottom: 10px; }
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; font-size: 14px; }
        .password-input-wrapper { position: relative; }
        .password-input-wrapper input { padding-right: 45px; }
        .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6c757d; cursor: pointer; font-size: 16px; padding: 5px; }
        .button-group { display: flex; gap: 10px; margin-top: 30px; }
        .button-group .btn { flex: 1; justify-content: center; padding: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-hospital"></i> 協立病院ポータル</h1>
            <div class="header-actions">
                <span class="welcome">ようこそ、<?= htmlspecialchars($current_user['username']) ?>さん</span>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-home"></i> ホームへ戻る</a>
            </div>
        </header>
        <div class="password-container">
            <div class="password-box">
                <div class="password-header">
                    <i class="fas fa-key"></i>
                    <h2>パスワード変更</h2>
                    <div class="user-info">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($current_user['username']) ?> (職員ID: <?= htmlspecialchars($current_user['user_id']) ?>)
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($error) ?></span></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($success) ?></span></div>
                <?php endif; ?>

                <form method="POST" action="change_password.php">
                    <div class="form-group">
                        <label for="current_password"><i class="fas fa-lock"></i> 現在のパスワード</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                            <button type="button" class="toggle-password" onclick="togglePassword('current_password')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password"><i class="fas fa-key"></i> 新しいパスワード</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="new_password" name="new_password" class="form-control" required autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePassword('new_password')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-key"></i> 新しいパスワード（確認用）</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="button-group">
                        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> 戻る</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> パスワードを変更</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('.toggle-password i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>


