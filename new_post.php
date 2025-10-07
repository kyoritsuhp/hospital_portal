<?php
// ファイル名称: new_post.php
// 更新日時: 2025-10-06

require_once 'config.php';

// ログイン確認
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// 投稿処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $importance = $_POST['importance'] ?? 'notice';
    $display_start = $_POST['display_start'] ?? null;
    $display_end = $_POST['display_end'] ?? null;
    $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']); // ホスト名を取得

    // バリデーション
    if (empty($title)) {
        $error = 'タイトルは必須項目です。';
    } elseif (empty($content)) {
        $error = '内容は必須項目です。';
    } elseif (!in_array($importance, ['important', 'notice', 'contact'])) {
        $error = '重要度の選択が正しくありません。';
    } else {
        try {
            // トランザクション開始
            $pdo->beginTransaction();
            
            // 投稿を保存
            $stmt = $pdo->prepare("
                INSERT INTO notices (title, content, importance, display_start, display_end, created_by, hostname) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title,
                $content,
                $importance,
                empty($display_start) ? null : $display_start,
                empty($display_end) ? null : $display_end,
                $_SESSION['user_id'],
                $hostname // ホスト名をバインド
            ]);
            
            $notice_id = $pdo->lastInsertId();
            
            // ファイルアップロード処理
            $upload_success = true;
            $upload_errors = [];
            
            if (!empty($_FILES['attachments']['name'][0])) {
                for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        // ファイルサイズチェック（10MB制限）
                        if ($_FILES['attachments']['size'][$i] > 10 * 1024 * 1024) {
                            $upload_errors[] = $_FILES['attachments']['name'][$i] . ' (ファイルサイズが大きすぎます)';
                            continue;
                        }
                        
                        $file = [
                            'name' => $_FILES['attachments']['name'][$i],
                            'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                            'size' => $_FILES['attachments']['size'][$i],
                            'type' => $_FILES['attachments']['type'][$i],
                            'error' => $_FILES['attachments']['error'][$i]
                        ];
                        
                        if (!handleFileUpload($file, $notice_id)) {
                            $upload_errors[] = $_FILES['attachments']['name'][$i];
                            $upload_success = false;
                        }
                    }
                }
            }
            
            if ($upload_success && empty($upload_errors)) {
                // すべて成功
                $pdo->commit();
                $message = '投稿が正常に作成されました。';
                // 成功時はフォームをリセット
                $_POST = [];
            } else {
                // 一部ファイルでエラー
                $pdo->commit(); // 投稿自体は保存
                if (!empty($upload_errors)) {
                    $message = '投稿は作成されましたが、以下のファイルのアップロードに失敗しました: ' . implode(', ', $upload_errors);
                } else {
                    $message = '投稿は作成されましたが、一部のファイルのアップロードに失敗しました。';
                }
            }
            
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'データベースエラーが発生しました。もう一度お試しください。';
            error_log("Database error in new_post.php: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'システムエラーが発生しました。もう一度お試しください。';
            error_log("General error in new_post.php: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規投稿 - 院内ポータルサイト</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-hospital"></i> 院内ポータルサイト</h1>
            <div class="header-actions">
                <span class="welcome">ようこそ、<?= htmlspecialchars(getCurrentUser()['username']) ?>さん</span>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> ホームに戻る
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> ログアウト
                </a>
            </div>
        </header>

        <div class="main-content" style="grid-template-columns: 1fr;">
            <main class="content">
                <div class="page-header">
                    <h2><i class="fas fa-plus"></i> 新規投稿作成</h2>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($message) ?>
                        <a href="index.php" style="margin-left: 15px;">
                            <i class="fas fa-arrow-right"></i> ホームで確認する
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <form method="POST" enctype="multipart/form-data" id="postForm">
                        <div class="form-group">
                            <label for="title">
                                <i class="fas fa-heading"></i> タイトル <span style="color: #dc3545;">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                class="form-control" 
                                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                placeholder="投稿のタイトルを入力してください"
                                required
                                maxlength="255"
                            >
                        </div>

                        <div class="form-group">
                            <label for="content">
                                <i class="fas fa-edit"></i> 内容 <span style="color: #dc3545;">*</span>
                            </label>
                            <textarea 
                                id="content" 
                                name="content" 
                                class="form-control" 
                                rows="8"
                                placeholder="投稿の内容を入力してください"
                                required
                            ><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-exclamation-circle"></i> 重要度 <span style="color: #dc3545;">*</span>
                            </label>
                            <div class="importance-options">
                                <div class="importance-option important">
                                    <input 
                                        type="radio" 
                                        id="important" 
                                        name="importance" 
                                        value="important"
                                        <?= (($_POST['importance'] ?? 'notice') === 'important') ? 'checked' : '' ?>
                                    >
                                    <label for="important">
                                        <i class="fas fa-exclamation-triangle"></i> 重要
                                    </label>
                                </div>
                                <div class="importance-option notice">
                                    <input 
                                        type="radio" 
                                        id="notice" 
                                        name="importance" 
                                        value="notice"
                                        <?= (($_POST['importance'] ?? 'notice') === 'notice') ? 'checked' : '' ?>
                                    >
                                    <label for="notice">
                                        <i class="fas fa-info-circle"></i> 周知
                                    </label>
                                </div>
                                <div class="importance-option contact">
                                    <input 
                                        type="radio" 
                                        id="contact" 
                                        name="importance" 
                                        value="contact"
                                        <?= (($_POST['importance'] ?? 'notice') === 'contact') ? 'checked' : '' ?>
                                    >
                                    <label for="contact">
                                        <i class="fas fa-phone"></i> 連絡
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label for="display_start">
                                    <i class="fas fa-calendar-alt"></i> 表示開始日
                                </label>
                                <input 
                                    type="date" 
                                    id="display_start" 
                                    name="display_start" 
                                    class="form-control"
                                    value="<?= htmlspecialchars($_POST['display_start'] ?? '') ?>"
                                >
                                <small style="color: #6c757d; font-size: 11px;">
                                    未設定の場合は即座に表示されます
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="display_end">
                                    <i class="fas fa-calendar-times"></i> 表示終了日
                                </label>
                                <input 
                                    type="date" 
                                    id="display_end" 
                                    name="display_end" 
                                    class="form-control"
                                    value="<?= htmlspecialchars($_POST['display_end'] ?? '') ?>"
                                >
                                <small style="color: #6c757d; font-size: 11px;">
                                    未設定の場合は無期限で表示されます
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="attachments">
                                <i class="fas fa-paperclip"></i> 添付ファイル
                            </label>
                            <input 
                                type="file" 
                                id="attachments" 
                                name="attachments[]" 
                                class="form-control file-input"
                                multiple
                                accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt"
                            >
                            <small style="color: #6c757d; font-size: 11px; display: block; margin-top: 5px;">
                                対応形式: 画像(JPG,PNG,GIF)、PDF、Office文書、テキストファイル<br>
                                複数ファイルの選択が可能です
                            </small>
                            <div id="file-preview" style="margin-top: 10px;"></div>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 30px;">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> キャンセル
                            </a>
                            
                            <div style="display: flex; gap: 10px;">
                                <button type="button" id="previewBtn" class="btn btn-outline">
                                    <i class="fas fa-eye"></i> プレビュー
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> 投稿する
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>