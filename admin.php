<?php
// ファイル名称: admin.php
// 更新日時: 2025-10-06

require_once 'config.php';

// ログイン確認
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// POSTアクション処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notice_id = $_POST['notice_id'] ?? 0;
    
    switch ($action) {
        case 'delete':
            try {
                // 添付ファイルをサーバーから削除
                $stmt = $pdo->prepare("SELECT file_path FROM notice_attachments WHERE notice_id = ?");
                $stmt->execute([$notice_id]);
                $attachments = $stmt->fetchAll();
                
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment['file_path'])) {
                        unlink($attachment['file_path']);
                    }
                }
                
                // 投稿をDBから削除 (関連する添付ファイルもCASCADEで削除される)
                $stmt = $pdo->prepare("DELETE FROM notices WHERE id = ?");
                $stmt->execute([$notice_id]);
                $message = '投稿を削除しました。';
            } catch (PDOException $e) {
                $error = '削除に失敗しました。' . $e->getMessage();
            }
            break;
            
        case 'toggle_visibility':
            try {
                $stmt = $pdo->prepare("UPDATE notices SET is_visible = !is_visible WHERE id = ?");
                $stmt->execute([$notice_id]);
                $message = '表示設定を変更しました。';
            } catch (PDOException $e) {
                $error = '設定変更に失敗しました。';
            }
            break;
    }
}

// 投稿一覧取得
$stmt = $pdo->prepare("
    SELECT n.*, u.username,
           (SELECT COUNT(*) FROM notice_attachments WHERE notice_id = n.id) as attachment_count
    FROM notices n 
    LEFT JOIN users u ON n.created_by = u.id 
    GROUP BY n.id
    ORDER BY n.created_at DESC
");
$stmt->execute();
$notices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - 協立病院ポータル</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-hospital"></i> 協立病院ポータル</h1>
            <div class="header-actions">
                <span class="welcome">ようこそ、<?= htmlspecialchars(getCurrentUser()['username']) ?>さん</span>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> ホーム
                </a>
                <a href="new_post.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> 新規投稿
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> ログアウト
                </a>
            </div>
        </header>

        <div class="admin-container">
            <div class="admin-header">
                <h2><i class="fas fa-cogs"></i> 管理画面</h2>
                <div>
                    <span style="color: #6c757d; font-size: 12px;">
                        投稿数: <?= count($notices) ?>件
                    </span>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success" style="padding: 12px; border-radius: 6px; background: #d4edda; color: #155724; margin-bottom: 15px;">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error" style="padding: 12px; border-radius: 6px; background: #f8d7da; color: #721c24; margin-bottom: 15px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="admin-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>タイトル</th>
                            <th>重要度</th>
                            <th>作成者</th>
                            <th>作成日</th>
                            <th>表示期間</th>
                            <th>状態</th>
                            <th>添付</th>
                            <th>更新端末</th>
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notices)): ?>
                            <tr><td colspan="10" style="text-align: center; padding: 20px;">投稿はありません。</td></tr>
                        <?php else: ?>
                            <?php foreach ($notices as $notice): ?>
                                <?php
                                    $status_class = 'status-hidden';
                                    $status_text = '非表示';
                                    $today = date('Y-m-d');

                                    $is_date_active = (empty($notice['display_start']) || $notice['display_start'] <= $today) &&
                                                      (empty($notice['display_end']) || $notice['display_end'] >= $today);

                                    if ($notice['is_visible'] && $is_date_active) {
                                        $status_class = 'status-visible';
                                        $status_text = '表示中';
                                    }
                                ?>
                                <tr>
                                    <td><?= $notice['id'] ?></td>
                                    <td>
                                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($notice['title']) ?>">
                                            <?= htmlspecialchars($notice['title']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="importance-badge" style="background-color: <?= getImportanceColor($notice['importance']) ?>">
                                            <?= getImportanceLabel($notice['importance']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($notice['username']) ?></td>
                                    <td><?= date('Y/m/d', strtotime($notice['created_at'])) ?></td>
                                    <td style="font-size: 11px;">
                                        <?= $notice['display_start'] ? date('Y/m/d', strtotime($notice['display_start'])) : '−' ?>
                                        〜
                                        <?= $notice['display_end'] ? date('Y/m/d', strtotime($notice['display_end'])) : '−' ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $status_class ?>">
                                            <?= $status_text ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($notice['attachment_count'] > 0): ?>
                                            <i class="fas fa-paperclip" title="<?= $notice['attachment_count'] ?>件"></i>
                                        <?php else: ?>
                                            <i class="fas fa-minus" style="color: #ccc;"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($notice['hostname'] ?? 'N/A') ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_post.php?id=<?= $notice['id'] ?>" class="btn btn-outline" style="border-color:#007bff; color:#007bff" title="編集">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('表示設定を変更しますか？')">
                                                <input type="hidden" name="action" value="toggle_visibility">
                                                <input type="hidden" name="notice_id" value="<?= $notice['id'] ?>">
                                                <button type="submit" class="btn <?= $notice['is_visible'] ? 'btn-secondary' : 'btn-primary' ?>" title="<?= $notice['is_visible'] ? '非表示にする' : '表示する' ?>">
                                                    <i class="fas <?= $notice['is_visible'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('この投稿を完全に削除します。よろしいですか？')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="notice_id" value="<?= $notice['id'] ?>">
                                                <button type="submit" class="btn btn-danger" title="削除">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>