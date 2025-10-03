<?php
// ファイル名称: change_password.php
// 更新日時: 2025-10-03

require_once 'config.php';

// ログインチェック
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

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
    } elseif (strlen($new_password) < 4) {
        $error = '新しいパスワードは4文字以上で入力してください。';
    } else {
        // 現在のユーザー情報を取得
        $user = getCurrentUser();
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $user_data = $stmt->fetch();
        
        // 現在のパスワードを確認
        if ($user_data['password'] !== $current_password) {
            $error = '現在のパスワードが正しくありません。';
        } else {
            // パスワードを更新
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$new_password, $user['id']])) {
                $success = 'パスワードを変更しました。';
            } else {
                $error = 'パスワードの変更に失敗しました。';
            }
        }
    }
}

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワード変更 - 協立病院ポータルサイト</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .password-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
        }
        
        .password-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .password-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .password-header i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .password-header h2 {
            color: #495057;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .user-info {
            background: rgba(102, 126, 234, 0.1);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            color: #495057;
            margin-bottom: 10px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert i {
            font-size: 18px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #667eea;
        }
        
        .password-input-wrapper {
            position: relative;
        }
        
        .password-input-wrapper input {
            padding-right: 45px;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
            transition: color 0.3s ease;
        }
        
        .toggle-password:hover {
            color: #495057;
        }
        
        .password-requirements {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #6c757d;
            margin-top: 8px;
        }
        
        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }
        
        .password-requirements li {
            margin: 3px 0;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .button-group .btn {
            flex: 1;
            justify-content: center;
            padding: 12px;
        }
        
        .back-button {
            background: #6c757d;
            color: white;
        }
        
        .back-button:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-hospital"></i> 協立病院ポータルサイト</h1>
            <div class="header-actions">
                <span class="welcome">ようこそ、<?= htmlspecialchars($current_user['username']) ?>さん</span>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> ホームへ戻る
                </a>
            </div>
        </header>

        <div class="password-container">
            <div class="password-box">
                <div class="password-header">
                    <i class="fas fa-key"></i>
                    <h2>パスワード変更</h2>
                    <div class="user-info">
                        <i class="fas fa-user"></i> 
                        <?= htmlspecialchars($current_user['username']) ?>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="change_password.php">
                    <div class="form-group">
                        <label for="current_password">
                            <i class="fas fa-lock"></i>現在のパスワード
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   class="form-control" 
                                   required
                                   autocomplete="current-password">
                            <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">
                            <i class="fas fa-key"></i>新しいパスワード
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   class="form-control" 
                                   required
                                   autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-requirements">
                            <strong><i class="fas fa-info-circle"></i> パスワードの要件:</strong>
                            <ul>
                                <li>4文字以上で入力してください</li>
                                <li>推奨: 英数字と記号を組み合わせてください</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-key"></i>新しいパスワード（確認用）
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-control" 
                                   required
                                   autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="button-group">
                        <a href="index.php" class="btn back-button">
                            <i class="fas fa-arrow-left"></i> キャンセル
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> パスワードを変更
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.parentElement.querySelector('.toggle-password');
            const icon = button.querySelector('i');
            
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

        // パスワード確認のリアルタイムチェック
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            confirmPassword.addEventListener('input', function() {
                if (newPassword.value && confirmPassword.value) {
                    if (newPassword.value === confirmPassword.value) {
                        confirmPassword.style.borderColor = '#28a745';
                    } else {
                        confirmPassword.style.borderColor = '#dc3545';
                    }
                } else {
                    confirmPassword.style.borderColor = '#ced4da';
                }
            });

            newPassword.addEventListener('input', function() {
                if (confirmPassword.value) {
                    if (newPassword.value === confirmPassword.value) {
                        confirmPassword.style.borderColor = '#28a745';
                    } else {
                        confirmPassword.style.borderColor = '#dc3545';
                    }
                }
            });
        });
    </script>
</body>
</html>
