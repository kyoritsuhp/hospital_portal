<?php
// ファイル名称: config.php
// 更新日時: 2025-10-03

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

// --- ▼▼▼ タイムアウト処理を追加 ▼▼▼ ---
// タイムアウト時間を10分（600秒）に設定
define('SESSION_TIMEOUT', 600);

// 最終アクセス時刻が記録されている場合
if (isset($_SESSION['last_activity'])) {
    // 現在時刻との差分を計算
    $elapsed_time = time() - $_SESSION['last_activity'];
    
    // タイムアウト時間を超えていればセッションを破棄
    if ($elapsed_time > SESSION_TIMEOUT) {
        // セッション変数を空にする
        $_SESSION = [];
        // セッションを完全に破棄
        session_destroy();
    }
}

// 最終アクセス時刻を更新
$_SESSION['last_activity'] = time();
// --- ▲▲▲ タイムアウト処理を追加 ▲▲▲ ---


// ログイン状態確認
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// ユーザー情報取得
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    // last_activityが更新されてしまうので、セッションから直接返す
    // return ['id' => $_SESSION['user_id'], 'username' => $_SESSION['user']];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();

}

// 添付ファイルアップロード処理
function handleFileUpload($file, $notice_id) {
    global $pdo;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        $stmt = $pdo->prepare("
            INSERT INTO notice_attachments (notice_id, file_name, file_path, file_size, file_type) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $notice_id,
            $file['name'],
            $file_path,
            $file['size'],
            $file['type']
        ]);
    }
    
    return false;
}

// 重要度に応じた色取得
function getImportanceColor($importance) {
    switch ($importance) {
        case 'important': return '#ff4444';
        case 'notice': return '#ffcc00';
        case 'contact': return '#4444ff';
        default: return '#666666';
    }
}

// 重要度に応じた日本語表示
function getImportanceLabel($importance) {
    switch ($importance) {
        case 'important': return '重要';
        case 'notice': return '周知';
        case 'contact': return '連絡';
        default: return '一般';
    }
}
?>