<?php
// ファイル名称: config.php
// 更新日時: 2025-10-08 (jinji連携改修)

// データベース設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'hospital_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

// データベース接続
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// セッション開始
session_start();

// --- タイムアウト処理 ---
define('SESSION_TIMEOUT', 600); // 10分

if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];
    if ($elapsed_time > SESSION_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
    }
}
$_SESSION['last_activity'] = time();

// ログイン状態確認
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * 現在ログインしているユーザー情報を取得する
 * @return array|null ユーザー情報の連想配列、またはnull
 * NOTE: 認証はjinji.staffテーブルで行われますが、セッションには
 * hospital_portal.usersテーブルのIDとユーザー名が保存されます。
 * これにより、ポータル内の投稿者記録などの既存機能との整合性を保ちます。
 */
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// 添付ファイルアップロード処理
function handleFileUpload($file, $notice_id) {
    global $pdo;
    
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        $stmt = $pdo->prepare(
            "INSERT INTO notice_attachments (notice_id, file_name, file_path, file_size, file_type) 
             VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$notice_id, $file['name'], $file_path, $file['size'], $file['type']]);
    }
    
    return false;
}

// 重要度に応じた色取得
function getImportanceColor($importance) {
    $colors = ['important' => '#ff4444', 'notice' => '#ffcc00', 'contact' => '#4444ff'];
    return $colors[$importance] ?? '#666666';
}

// 重要度に応じた日本語表示
function getImportanceLabel($importance) {
    $labels = ['important' => '重要', 'notice' => '周知', 'contact' => '連絡'];
    return $labels[$importance] ?? '一般';
}
?>
