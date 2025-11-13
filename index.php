<?php
// ファイル名称: index.php
// 更新日時: 2025-10-03
// 不要メニュー項目を削除: 2025-10-15
// 健診問診票リンクの表示/非表示機能追加: 2025-10-20
// ★ 健診担当者リンク (admin=1 または kenshin=1 で表示) を修正: 2025-10-24
// (フィルター機能のJavaScriptを実装: 2025-10-31)
// 修正: 2025-11-04 (投稿成功メッセージの表示機能を追加)
// 修正: 2025-11-06 (archive.php へのリンクを追加)
// ★ レイアウト改修: 2025-11-06 (重要度・タイトルのグループ化、日時・投稿者の右寄せ)
// ★ 添付ファイル表示改修: 2025-11-06 (H4見出しを削除し、アイコンとファイル名を横並び)

require_once 'config.php';

// ★ セッションから成功メッセージを取得
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // 再読み込み時に表示させない
}

// 周知掲示板の取得（表示期間内かつ表示フラグONのもの）
$stmt = $pdo->prepare("
    SELECT n.*, u.username,
           GROUP_CONCAT(
               CONCAT(na.id, ':', na.file_name, ':', na.file_path, ':', na.file_type)
               SEPARATOR '|'
           ) as attachments
    FROM notices n
    LEFT JOIN users u ON n.created_by = u.id
    LEFT JOIN notice_attachments na ON n.id = na.notice_id
    WHERE n.is_visible = 1
    AND (n.display_start IS NULL OR n.display_start <= CURDATE())
    AND (n.display_end IS NULL OR n.display_end >= CURDATE())
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
    <title>協立病院ポータル</title>
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-hospital"></i> 協立病院ポータル</h1>
            <div class="header-actions">
                <?php if (isLoggedIn()): ?>
                    <span class="welcome">ようこそ、<?= htmlspecialchars(getCurrentUser()['username']) ?>さん</span>
                    <a href="new_post.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> 新規投稿
                    </a>
                    <a href="admin.php" class="btn btn-secondary">
                        <i class="fas fa-cog"></i> 管理画面
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> ログアウト
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> ログイン
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($success_message): ?>
            <div class="alert alert-success" style="margin: 20px; padding: 12px 15px; border-radius: 6px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; display: flex; align-items: center; gap: 10px;">
                <?php
                    // common_circle-ok.svg を読み込む (change_password.php と同様の方式)
                    $icon_path = 'icons/common_circle-ok.svg';
                    if (file_exists($icon_path)) {
                        $svg_content = file_get_contents($icon_path);
                        echo str_replace(
                            '<svg', 
                            '<svg style="width: 16px; height: 16px; fill: currentColor; flex-shrink: 0;"', 
                            $svg_content
                        );
                    } 
                ?>
                <span><?= htmlspecialchars($success_message) ?></span>
            </div>
        <?php endif; ?>

        <div class="main-content">
            <nav class="sidebar sidebar-left">
                <h3><i class="fas fa-bars"></i> メニュー</h3>
                <ul class="menu-list">
                    <li><a href="index.php"><i class="fas fa-home"></i> ホーム</a></li>
                    <?php // 健診問診票リンクの表示制御 (役職と kenshin/config.json を参照) ?>
                    <?php if (shouldShowKenshinLink()): ?>
                    <li><a href="http://localhost/kenshin/"><i class="fas fa-file-medical"></i> 健診問診票</a></li>
                    <?php endif; ?>

                </ul>
            </nav>

            <main class="content">
                <div class="page-header">
                    <h2><i class="fas fa-bullhorn"></i> 周知掲示板</h2>
                </div>

                <div class="notices-container">
                    <?php if (empty($notices)): ?>
                        <div class="no-notices">
                            <i class="fas fa-info-circle"></i>
                            <p>現在表示する投稿はありません。</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notices as $index => $notice): ?>
                            <div class="notice-item <?= $index >= 10 ? 'notice-hidden' : '' ?>"
                                 data-importance="<?= htmlspecialchars($notice['importance']) ?>"
                                 style="border-left: 4px solid <?= getImportanceColor($notice['importance']) ?>">

                                <!-- レイアウト改修 (index.css と連動) -->
                                <div class="notice-header">
                                    <!-- 左側グループ: 重要度 + タイトル -->
                                    <div class="notice-title-group">
                                        <span class="importance-badge" style="background-color: <?= getImportanceColor($notice['importance']) ?>">
                                            <?= getImportanceLabel($notice['importance']) ?>
                                        </span>
                                        <h3 class="notice-title" title="<?= htmlspecialchars($notice['title']) ?>">
                                            <?= htmlspecialchars($notice['title']) ?>
                                        </h3>
                                    </div>
                                    
                                    <!-- 右側グループ: 日時 + 投稿者 -->
                                    <div class="notice-meta-right">
                                        <span class="notice-date">
                                            <i class="fas fa-clock"></i> <?= date('Y/m/d H:i', strtotime($notice['created_at'])) ?>
                                        </span>
                                        <span class="notice-author">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($notice['username']) ?>
                                        </span>
                                    </div>
                                </div>
                                <!-- ここまでレイアウト改修 -->

                                <div class="notice-content">
                                    <?= nl2br(htmlspecialchars($notice['content'])) ?>
                                </div>

                                <?php if ($notice['attachments']): ?>
                                    <div class="notice-attachments">
                                        <!-- ★ <h4> タグを削除 -->
                                        <div class="attachment-list">
                                            <!-- ★ 先頭のクリップアイコンを削除 (ループ内に移動するため) -->
                                            <?php
                                            $attachments = explode('|', $notice['attachments']);
                                            foreach ($attachments as $attachment):
                                                if (empty($attachment)) continue;
                                                list($att_id, $file_name, $file_path, $file_type) = explode(':', $attachment, 4);
                                            ?>
                                                <a href="<?= htmlspecialchars($file_path) ?>" target="_blank" class="attachment-link">
                                                    <?php
                                                    // ★ 常にクリップアイコンを表示するように変更
                                                    $icon = 'fas fa-paperclip';
                                                    ?>
                                                    <i class="<?= $icon ?>"></i>
                                                    <?= htmlspecialchars($file_name) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($notices) > 10): ?>
                            <div class="toggle-container">
                                <button id="toggleNotices" class="btn btn-outline">
                                    <i class="fas fa-chevron-down"></i>
                                    <span>さらに表示する (<?= count($notices) - 10 ?>件)</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>

            <aside class="sidebar sidebar-right">
                <h3><i class="fas fa-link"></i> クイックリンク</h3>
                <div class="quick-links">
                    <a href="#notices" class="quick-link">
                        <i class="fas fa-bullhorn"></i>
                        <span>周知掲示板</span>
                    </a>
                    
                    <!-- ★ ログイン状態に関わらず表示するよう変更 -->
                    <a href="archive.php" class="quick-link">
                        <i class="fas fa-archive"></i>
                        <span>過去の投稿</span>
                    </a>

                    <?php if (isLoggedIn()): ?>
                        <a href="new_post.php" class="quick-link">
                            <i class="fas fa-edit"></i>
                            <span>新規投稿</span>
                        </a>
                        <a href="admin.php" class="quick-link">
                            <i class="fas fa-cogs"></i>
                            <span>管理画面</span>
                        </a>

                        <?php // ★ 健診担当者リンク (admin=1 または kenshin=1 の場合) - 修正 ?>
                        <?php if ((isset($_SESSION['admin']) && $_SESSION['admin'] == 1) || (isset($_SESSION['kenshin']) && $_SESSION['kenshin'] == 1)): ?>
                        <a href="../kenshin/admin_dashboard.php" class="quick-link" target="_blank">
                            <i class="fas fa-clipboard-list"></i>
                            <span>健診担当者</span>
                        </a>
                        <?php endif; ?>
                        <?php // ★ ここまで修正 ?>

                        <a href="change_password.php" class="quick-link">
                            <i class="fas fa-key"></i>
                            <span>パスワード変更</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="notice-summary">
                    <h4><i class="fas fa-chart-pie"></i> 投稿状況</h4>
                    <div class="summary-stats">
                        <div class="stat-item important" onclick="filterNotices('important')" style="cursor: pointer;" title="重要な投稿のみ表示">
                            <span class="stat-number"><?= count(array_filter($notices, function($n) { return $n['importance'] === 'important'; })) ?></span>
                            <span class="stat-label">重要</span>
                        </div>
                        <div class="stat-item notice" onclick="filterNotices('notice')" style="cursor: pointer;" title="周知投稿のみ表示">
                            <span class="stat-number"><?= count(array_filter($notices, function($n) { return $n['importance'] === 'notice'; })) ?></span>
                            <span class="stat-label">周知</span>
                        </div>
                        <div class="stat-item contact" onclick="filterNotices('contact')" style="cursor: pointer;" title="連絡投稿のみ表示">
                            <span class="stat-number"><?= count(array_filter($notices, function($n) { return $n['importance'] === 'contact'; })) ?></span>
                            <span class="stat-label">連絡</span>
                        </div>
                    </div>
                    <div style="margin-top: 10px; text-align: center;">
                        <button onclick="filterNotices('all')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px;">
                            <i class="fas fa-list"></i> すべて表示
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleNotices');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    const hiddenNotices = document.querySelectorAll('.notice-item.notice-hidden');
                    const isHidden = toggleBtn.classList.toggle('toggled');
                    
                    hiddenNotices.forEach(notice => {
                        // フィルターで非表示になっているものは、トグル操作でも非表示を維持
                        if (notice.style.display !== 'none') {
                            notice.style.display = isHidden ? 'block' : '';
                        }
                    });

                    // フィルターが適用されている場合、トグルボタンは機能しないように見せる
                    // (実際にはトグルされるが、フィルターで非表示のものは表示されない)
                    const activeFilter = document.querySelector('.stat-item.stat-active');
                    if (activeFilter) {
                         // フィルター中は「さらに表示」ボタンを非表示にする
                         toggleBtn.style.display = 'none';
                    } else {
                        // フィルター解除時はボタンのテキストを更新
                        const icon = toggleBtn.querySelector('i');
                        const span = toggleBtn.querySelector('span');
                        
                        if (isHidden) {
                            icon.classList.remove('fa-chevron-down');
                            icon.classList.add('fa-chevron-up');
                            span.textContent = '折りたたむ';
                        } else {
                            icon.classList.remove('fa-chevron-up');
                            icon.classList.add('fa-chevron-down');
                            span.textContent = `さらに表示する (${hiddenNotices.length}件)`;
                        }
                    }
                });
            }
        });

        function filterNotices(importance) {
            const notices = document.querySelectorAll('.notice-item');
            const toggleBtnContainer = document.querySelector('.toggle-container');
            const statButtons = document.querySelectorAll('.stat-item');
            const allNotices = <?php echo count($notices); ?>;
            const initialVisibleCount = 10;

            // フィルターボタンのアクティブ状態を更新
            statButtons.forEach(btn => btn.classList.remove('stat-active'));
            if (importance !== 'all') {
                const activeButton = document.querySelector(`.stat-item.${importance}`);
                if (activeButton) {
                    activeButton.classList.add('stat-active');
                }
            }

            let visibleCount = 0;

            notices.forEach((notice, index) => {
                const noticeImportance = notice.getAttribute('data-importance');
                const isHiddenByDefault = notice.classList.contains('notice-hidden');
                
                if (importance === 'all' || noticeImportance === importance) {
                    // 「すべて」または重要度が一致する場合
                    
                    if (importance === 'all') {
                        // 「すべて」の場合、デフォルトの10件表示ロジックに従う
                        if (index < initialVisibleCount) {
                            notice.style.display = 'block'; // 表示
                        } else {
                            // 11件目以降は .notice-hidden クラスに従う
                            notice.style.display = ''; // CSSの .notice-hidden に従う
                        }
                    } else {
                        // フィルター中の場合、件数制限なくすべて表示
                        notice.style.display = 'block';
                    }
                    visibleCount++;
                    
                } else {
                    // 重要度が一致しない場合
                    notice.style.display = 'none'; // 非表示
                }
            });

            // 「さらに表示」ボタンの表示/非表示を制御
            if (toggleBtnContainer) {
                if (importance === 'all' && allNotices > initialVisibleCount) {
                    // 「すべて」表示で、10件より多い場合のみ表示
                    toggleBtnContainer.style.display = 'block';
                    
                    // フィルター解除時にボタンの状態をリセット
                    const toggleBtn = document.getElementById('toggleNotices');
                    toggleBtn.classList.remove('toggled');
                    toggleBtn.querySelector('i').classList.remove('fa-chevron-up');
                    toggleBtn.querySelector('i').classList.add('fa-chevron-down');
                    toggleBtn.querySelector('span').textContent = `さらに表示する (${allNotices - initialVisibleCount}件)`;

                } else {
                    // フィルター中、または10件以下の場合は非表示
                    toggleBtnContainer.style.display = 'none';
                }
            }
            
            // 投稿がない場合のメッセージ表示
            const noNoticesMessage = document.querySelector('.no-notices');
            if (noNoticesMessage) {
                 if (visibleCount === 0 && importance !== 'all') {
                     // フィルター結果が0件の場合
                     noNoticesMessage.style.display = 'block';
                     noNoticesMessage.querySelector('p').textContent = 'この条件に一致する投稿はありません。';
                 } else if (allNotices === 0) {
                     // もともと投稿が0件の場合
                     noNoticesMessage.style.display = 'block';
                     noNoticesMessage.querySelector('p').textContent = '現在表示する投稿はありません。';
                 } else {
                     // 投稿がある場合
                     noNoticesMessage.style.display = 'none';
                 }
            }
        }

        // 初期ロード時に、投稿が0件の場合のメッセージを正しく処理
        document.addEventListener('DOMContentLoaded', function() {
            if (<?php echo count($notices); ?> === 0) {
                const noNoticesMessage = document.querySelector('.no-notices');
                if (noNoticesMessage) {
                    noNoticesMessage.style.display = 'block';
                }
            }
        });
    </script>
</body>
</html>