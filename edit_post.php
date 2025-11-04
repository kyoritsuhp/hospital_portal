<?php
// ファイル名称: edit_post.php
// 更新日時: 2025-10-10 (編集権限機能の追加)
// 修正: 2025-11-04 (権限チェックを $_SESSION['admin'] 基準に変更)

require_once 'config.php';

// ログイン確認
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$notice_id = $_GET['id'] ?? 0;

// IDが無効な場合は管理画面にリダイレクト
if (!$notice_id) {
    header('Location: admin.php');
    exit;
}

// 投稿データを取得
$stmt = $pdo->prepare("SELECT * FROM notices WHERE id = ?");
$stmt->execute([$notice_id]);
$notice = $stmt->fetch();

// 投稿が存在しない場合はリダイレクト
if (!$notice) {
    header('Location: admin.php');
    exit;
}

// --- 権限チェック ---
$currentUser = getCurrentUser();

// ★ 投稿者本人か、管理者(セッションのadminフラグ)でなければアクセスを拒否
// (isset($_SESSION['admin']) は login.php でセットされる)
if ( !(isset($_SESSION['admin']) && $_SESSION['admin'] == 1) && $currentUser['id'] != $notice['created_by'] ) {
    $_SESSION['error_message'] = 'この投稿を編集する権限がありません。';
    header('Location: admin.php');
    exit;
}
// --- 権限チェックここまで ---

// 投稿更新処理
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
    } else {
        try {
            $pdo->beginTransaction();

            // 投稿内容を更新
            $stmt = $pdo->prepare("
                UPDATE notices 
                SET title = ?, content = ?, importance = ?, display_start = ?, display_end = ?, hostname = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $title,
                $content,
                $importance,
                empty($display_start) ? null : $display_start,
                empty($display_end) ? null : $display_end,
                $hostname, // ホスト名をバインド
                $notice_id
            ]);

            // 添付ファイルの削除処理
            if (!empty($_POST['delete_attachments'])) {
                foreach ($_POST['delete_attachments'] as $attachment_id) {
                    $stmt = $pdo->prepare("SELECT file_path FROM notice_attachments WHERE id = ? AND notice_id = ?");
                    $stmt->execute([$attachment_id, $notice_id]);
                    $attachment = $stmt->fetch();

                    if ($attachment && file_exists($attachment['file_path'])) {
                        unlink($attachment['file_path']);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM notice_attachments WHERE id = ?");
                    $stmt->execute([$attachment_id]);
                }
            }
            
            // 新規ファイルアップロード処理
            if (!empty($_FILES['attachments']['name'][0])) {
                for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['attachments']['name'][$i],
                            'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                            'size' => $_FILES['attachments']['size'][$i],
                            'type' => $_FILES['attachments']['type'][$i],
                            'error' => $_FILES['attachments']['error'][$i]
                        ];
                        handleFileUpload($file, $notice_id);
                    }
                }
            }

            $pdo->commit();
            $message = '投稿を更新しました。';
            
            // 更新後のデータを再取得してフォームに表示
            $stmt = $pdo->prepare("SELECT * FROM notices WHERE id = ?");
            $stmt->execute([$notice_id]);
            $notice = $stmt->fetch();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '更新中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}

// 添付ファイルを取得
$stmt = $pdo->prepare("SELECT * FROM notice_attachments WHERE notice_id = ?");
$stmt->execute([$notice_id]);
$attachments = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>投稿編集 - 院内ポータルサイト</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-hospital"></i> 院内ポータルサイト</h1>
            <div class="header-actions">
                <span class="welcome">ようこそ、<?= htmlspecialchars(getCurrentUser()['username']) ?>さん</span>
                <a href="admin.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> 管理画面に戻る</a>
            </div>
        </header>

        <div class="main-content" style="grid-template-columns: 1fr;">
            <main class="content">
                <div class="page-header">
                    <h2><i class="fas fa-edit"></i> 投稿編集 (ID: <?= htmlspecialchars($notice_id) ?>)</h2>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success" style="padding: 12px; border-radius: 6px; background: #d4edda; color: #155724;">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                        <a href="admin.php" style="margin-left: 15px;">管理画面で確認</a>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error" style="padding: 12px; border-radius: 6px; background: #f8d7da; color: #721c24;">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="title"><i class="fas fa-heading"></i> タイトル <span style="color: #dc3545;">*</span></label>
                            <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($notice['title']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="content"><i class="fas fa-edit"></i> 内容 <span style="color: #dc3545;">*</span></label>
                            <textarea id="content" name="content" class="form-control" rows="8" required><?= htmlspecialchars($notice['content']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-exclamation-circle"></i> 重要度 <span style="color: #dc3545;">*</span></label>
                            <div class="importance-options">
                                <div class="importance-option important">
                                    <input type="radio" id="important" name="importance" value="important" <?= $notice['importance'] === 'important' ? 'checked' : '' ?>>
                                    <label for="important"><i class="fas fa-exclamation-triangle"></i> 重要</label>
                                </div>
                                <div class="importance-option notice">
                                    <input type="radio" id="notice" name="importance" value="notice" <?= $notice['importance'] === 'notice' ? 'checked' : '' ?>>
                                    <label for="notice"><i class="fas fa-info-circle"></i> 周知</label>
                                </div>
                                <div class="importance-option contact">
                                    <input type="radio" id="contact" name="importance" value="contact" <?= $notice['importance'] === 'contact' ? 'checked' : '' ?>>
                                    <label for="contact"><i class="fas fa-phone"></i> 連絡</label>
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label for="display_start"><i class="fas fa-calendar-alt"></i> 表示開始日</label>
                                <input type="date" id="display_start" name="display_start" class="form-control" value="<?= htmlspecialchars($notice['display_start']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="display_end"><i class="fas fa-calendar-times"></i> 表示終了日</label>
                                <input type="date" id="display_end" name="display_end" class="form-control" value="<?= htmlspecialchars($notice['display_end']) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-paperclip"></i> 添付ファイル</label>
                            <?php if (!empty($attachments)): ?>
                                <div style="margin-bottom: 10px; background: #f8f9fa; padding: 10px; border-radius: 6px;">
                                    <strong>現在のファイル:</strong>
                                    <?php foreach ($attachments as $attachment): ?>
                                    <div style="margin: 5px 0;">
                                        <input type="checkbox" name="delete_attachments[]" value="<?= $attachment['id'] ?>" id="delete_<?= $attachment['id'] ?>">
                                        <label for="delete_<?= $attachment['id'] ?>"> 削除: <?= htmlspecialchars($attachment['file_name']) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <input type="file" id="attachments" name="attachments[]" class="form-control file-input" multiple>
                            <small style="color: #6c757d; font-size: 11px;">新しくファイルを追加するには、ここから選択してください。</small>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 30px;">
                            <a href="admin.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> キャンセル</a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 更新する</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>