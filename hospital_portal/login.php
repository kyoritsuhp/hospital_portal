<?php
// ファイル名称: login.php
// 更新日時: 2025-10-08 (jinji連携強化、情報同期処理追加)

require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($user_id) || empty($password)) {
        $error = '職員IDとパスワードを入力してください。';
    } else {
        try {
            // 1. jinjiデータベースに接続して職員認証
            $jinji_pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=jinji;charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            
            $stmt_jinji = $jinji_pdo->prepare("SELECT * FROM staff WHERE staff_id = ?");
            $stmt_jinji->execute([$user_id]);
            $staff = $stmt_jinji->fetch();
            
            // 2. 職員認証の実行
            if ($staff && $staff['staff_password'] === $password) {
                // --- 認証成功 ---
                // ポータル側のユーザー情報を同期（JITプロビジョニング＆更新）
                
                $stmt_portal = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt_portal->execute([$staff['staff_id']]);
                $portal_user = $stmt_portal->fetch();

                if ($portal_user) {
                    // 3a. ユーザーが存在する場合：情報が最新か確認し、必要なら更新
                    if ($portal_user['username'] !== $staff['name'] || $portal_user['password'] !== $staff['staff_password']) {
                        $stmt_update = $pdo->prepare(
                            "UPDATE users SET username = ?, password = ? WHERE id = ?"
                        );
                        $stmt_update->execute([$staff['name'], $staff['staff_password'], $portal_user['id']]);
                    }
                } else {
                    // 3b. ユーザーが存在しない場合：新規作成
                    $stmt_insert = $pdo->prepare(
                        "INSERT INTO users (user_id, password, username) VALUES (?, ?, ?)"
                    );
                    $stmt_insert->execute([$staff['staff_id'], $staff['staff_password'], $staff['name']]);
                }

                // 4. 最新のポータルユーザー情報を取得してセッションを生成
                $stmt_portal->execute([$staff['staff_id']]);
                $final_portal_user = $stmt_portal->fetch();

                if ($final_portal_user) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $final_portal_user['id'];
                    $_SESSION['user'] = $final_portal_user['username'];
                    $_SESSION['last_activity'] = time();

                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'ポータルユーザーアカウントの処理に失敗しました。';
                }

            } else {
                $error = '職員IDまたはパスワードが正しくありません。';
            }
        } catch (PDOException $e) {
            $error = 'システムエラーが発生しました。';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - 院内ポータルサイト</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2><i class="fas fa-hospital"></i> 院内ポータル ログイン</h2>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="user_id"><i class="fas fa-user"></i> 職員ID</label>
                    <input type="text" id="user_id" name="user_id" class="form-control" value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>" placeholder="職員IDを入力" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> パスワード</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="パスワードを入力" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> ログイン</button>
            </form>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> トップページに戻る</a>
        </div>
    </div>
    <style>
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</body>
</html>

