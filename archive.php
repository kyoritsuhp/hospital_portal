<?php
// ファイル名称: archive.php
// 生成日時: 2025-11-06
// 概要: 非表示または表示期間が終了した過去の投稿を検索・閲覧するページ
// 修正: 2025-11-06 (ログインチェックを解除し、一般公開)

require_once 'config.php';

// ★ ログインチェックを削除

// ログインしている場合のみユーザー情報を取得
$currentUser = isLoggedIn() ? getCurrentUser() : null;

// --- フィルター条件の取得 ---
$filter_importance = $_GET['importance'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_author = $_GET['author'] ?? ''; // 投稿者名 (username)
$filter_title = $_GET['title'] ?? '';

// --- ベースSQL ---
$sql = "
    SELECT n.*, u.username, u.user_id as creator_user_id,
           (SELECT COUNT(*) FROM notice_attachments WHERE notice_id = n.id) as attachment_count
    FROM notices n 
    LEFT JOIN users u ON n.created_by = u.id 
    WHERE (
        n.is_visible = 0 
        OR (n.display_end IS NOT NULL AND n.display_end < CURDATE())
    )
";
$params = [];

// --- 条件の追加 ---
if (!empty($filter_importance)) {
    $sql .= " AND n.importance = ?";
    $params[] = $filter_importance;
}
if (!empty($filter_date_from)) {
    // 作成日 (created_at) の日付部分で比較
    $sql .= " AND DATE(n.created_at) >= ?";
    $params[] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    // 作成日 (created_at) の日付部分で比較
    $sql .= " AND DATE(n.created_at) <= ?";
    $params[] = $filter_date_to;
}
if (!empty($filter_author)) {
    $sql .= " AND u.username LIKE ?";
    $params[] = '%' . htmlspecialchars($filter_author, ENT_QUOTES, 'UTF-8') . '%';
}
if (!empty($filter_title)) {
    $sql .= " AND n.title LIKE ?";
    $params[] = '%' . htmlspecialchars($filter_title, ENT_QUOTES, 'UTF-8') . '%';
}

$sql .= " GROUP BY n.id ORDER BY n.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notices = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "データベースエラー: ". $e->getMessage();
    $notices = [];
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>過去の投稿一覧 - 協立病院ポータル</title>
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="archive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-hospital"></i> 協立病院ポータル</h1>
            <div class="header-actions">
                <?php if ($currentUser): // ログインしている場合 ?>
                    <span class="welcome">ようこそ、<?= htmlspecialchars($currentUser['username']) ?>さん</span>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> ホーム
                </a>
                    <a href="admin.php" class="btn btn-secondary">
                        <i class="fas fa-cog"></i> 管理画面
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> ログアウト
                    </a>
                <?php else: // ログインしていない場合 ?>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> ホーム
                    </a>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> ログイン
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <div class="admin-container">
            <div class="admin-header">
                <h2><i class="fas fa-archive"></i> 過去の投稿一覧</h2>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error" style="padding: 12px; border-radius: 6px; background: #f8d7da; color: #721c24; margin-bottom: 15px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- フィルターフォーム -->
            <div class="filter-container">
                <form method="GET" action="archive.php">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="filter_title"><i class="fas fa-heading"></i> タイトル</label>
                            <input type="text" id="filter_title" name="title" class="form-control" value="<?= htmlspecialchars($filter_title) ?>">
                        </div>
                        <div class="form-group">
                            <label for="filter_author"><i class="fas fa-user"></i> 投稿者名</label>
                            <input type="text" id="filter_author" name="author" class="form-control" value="<?= htmlspecialchars($filter_author) ?>">
                        </div>
                        <div class="form-group">
                            <label for="filter_importance"><i class="fas fa-exclamation-circle"></i> 重要度</label>
                            <select id="filter_importance" name="importance" class="form-control">
                                <option value="">すべて</option>
                                <option value="important" <?= $filter_importance == 'important' ? 'selected' : '' ?>>重要</option>
                                <option value="notice" <?= $filter_importance == 'notice' ? 'selected' : '' ?>>周知</option>
                                <option value="contact" <?= $filter_importance == 'contact' ? 'selected' : '' ?>>連絡</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_date_from"><i class="fas fa-calendar-alt"></i> 作成日(From)</label>
                            <input type="date" id="filter_date_from" name="date_from" class="form-control" value="<?= htmlspecialchars($filter_date_from) ?>">
                        </div>
                        <div class="form-group">
                            <label for="filter_date_to"><i class="fas fa-calendar-times"></i> 作成日(To)</label>
                            <input type="date" id="filter_date_to" name="date_to" class="form-control" value="<?= htmlspecialchars($filter_date_to) ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <a href="archive.php" class="btn btn-secondary"><i class="fas fa-times"></i> リセット</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 検索</button>
                    </div>
                </form>
            </div>

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
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notices)): ?>
                            <tr><td colspan="9" style="text-align: center; padding: 20px;">
                                <?php if (empty($_GET)): ?>
                                    非表示の投稿はありません。
                                <?php else: ?>
                                    条件に一致する投稿はありません。
                                <?php endif; ?>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($notices as $notice): ?>
                                <?php
                                    // 状態判定
                                    $status_class = 'status-hidden';
                                    $status_text = '非表示';
                                    if ($notice['display_end'] && $notice['display_end'] < date('Y-m-d')) {
                                        $status_text = '期間終了';
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
                                    <td><?= date('Y/m/d H:i', strtotime($notice['created_at'])) ?></td>
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
                                    <td>
                                        <div class="action-buttons">
                                            <?php
                                                // ★ 権限チェック (ログインしている場合のみ)
                                                $canManage = false;
                                                if ($currentUser) {
                                                    $canManage = (isset($_SESSION['admin']) && $_SESSION['admin'] == 1) || $currentUser['id'] == $notice['created_by'];
                                                }
                                            ?>

                                            <?php if ($canManage): // ログインしており、かつ権限がある場合のみ編集ボタン表示 ?>
                                                <a href="edit_post.php?id=<?= $notice['id'] ?>" class="btn btn-outline" style="border-color:#007bff; color:#007bff" title="編集">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
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