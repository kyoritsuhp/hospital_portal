<?php
// ファイル名称: config.php
// 更新日時: 2025-10-08 (jinji連携改修)
// 健診リンク表示ロジック変更: 2025-10-20

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

/**
 * 健診問診票リンクを表示すべきか判断する
 * 1. ログイン中のユーザーの役職 (position) を確認
 * 2. 役職が '健診担当' なら true
 * 3. それ以外 (未ログイン含む) なら kenshin/config.json の設定に従う
 * @return bool
 */
function shouldShowKenshinLink() {
    // 1. ユーザーの役職をセッションから取得
    $user_position = $_SESSION['position'] ?? null;
    
    // 2. 役職が '健診担当' かチェック
    if ($user_position === '健診担当') {
        return true; // 健診担当は常に表示
    }
    
    // 3. それ以外 (未ログイン or 健診担当でない) 場合は、JSONファイルの設定を確認
    
    // このファイルの場所を基準に、kenshinディレクトリのconfig.jsonを指定
    $configPath = __DIR__ . '/../kenshin/config.json';
    
    if (!file_exists($configPath)) {
        // config.json が存在しない場合は、デフォルトで表示(true)
        return true;
    }
    
    $configJson = @file_get_contents($configPath);
    if ($configJson === false) {
        // 読み取り失敗時も、安全のため表示(true)
        return true; 
    }
    
    $config = json_decode($configJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // JSONデコード失敗時も、安全のため表示(true)
        return true;
    }
    
    // 'show_portal_link' が存在しないか、trueの場合に true を返す
    // 明示的に false が設定されている場合のみ false を返す
    return $config['show_portal_link'] ?? true;
}
?>