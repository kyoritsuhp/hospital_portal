<?php
// ファイル名称: login.php
// 更新日時: 2025-10-03

require_once 'config.php';

$error = '';

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($user_id) || empty($password)) {
        $error = 'ユーザーIDとパスワードを入力してください。';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            // パスワードはハッシュ化しないため直接比較
            if ($user && $user['password'] === $password) {
                // セッションハイジャック対策
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user['username'];
                $_SESSION['last_activity'] = time();

                header('Location: index.php');
                exit;
            } else {
                $error = 'ユーザーIDまたはパスワードが正しくありません。';
            }
        } catch (PDOException $e) {
            $error = 'システムエラーが発生しました。';
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
            <h2>
                <i class="fas fa-hospital"></i>
                院内ポータル ログイン
            </h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="user_id">
                        <i class="fas fa-user"></i> ユーザーID
                    </label>
                    <input 
                        type="text" 
                        id="user_id" 
                        name="user_id" 
                        class="form-control" 
                        value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>"
                        placeholder="ユーザーIDを入力"
                        required
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> パスワード
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="パスワードを入力"
                        required
                    >
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> ログイン
                </button>
            </form>
            
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> トップページに戻る
            </a>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef; font-size: 12px; color: #6c757d;">
                <strong>テスト用アカウント:</strong><br>
                ユーザーID: admin<br>
                パスワード: admin
            </div>
        </div>
    </div>

    <style>
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert i {
            font-size: 14px;
        }
    </style>
</body>
</html>
