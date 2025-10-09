<?php
// ファイル名称: index.php
// 更新日時: 2025-10-03

require_once 'config.php';

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
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        <div class="main-content">
            <nav class="sidebar sidebar-left">
                <h3><i class="fas fa-bars"></i> メニュー</h3>
                <ul class="menu-list">
                    <li><a href="index.php"><i class="fas fa-home"></i> ホーム</a></li>
                    <li><a href="calendar.php"><i class="fas fa-calendar"></i> カレンダー</a></li>
                    <li><a href="#"><i class="fas fa-users"></i> スタッフ一覧</a></li>
                    <li><a href="#"><i class="fas fa-phone"></i> 内線番号</a></li>
                    <li><a href="#"><i class="fas fa-file-medical"></i> 診療情報</a></li>
                    <li><a href="#"><i class="fas fa-chart-bar"></i> 統計情報</a></li>
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
                                
                                <div class="notice-header">
                                    <div class="notice-meta">
                                        <span class="importance-badge" style="background-color: <?= getImportanceColor($notice['importance']) ?>">
                                            <?= getImportanceLabel($notice['importance']) ?>
                                        </span>
                                        <span class="notice-date">
                                            <i class="fas fa-clock"></i> <?= date('Y/m/d H:i', strtotime($notice['created_at'])) ?>
                                        </span>
                                        <span class="notice-author">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($notice['username']) ?>
                                        </span>
                                    </div>
                                </div>

                                <h3 class="notice-title"><?= htmlspecialchars($notice['title']) ?></h3>
                                <div class="notice-content">
                                    <?= nl2br(htmlspecialchars($notice['content'])) ?>
                                </div>

                                <?php if ($notice['attachments']): ?>
                                    <div class="notice-attachments">
                                        <h4><i class="fas fa-paperclip"></i> 添付ファイル</h4>
                                        <div class="attachment-list">
                                            <?php 
                                            $attachments = explode('|', $notice['attachments']);
                                            foreach ($attachments as $attachment): 
                                                if (empty($attachment)) continue;
                                                list($att_id, $file_name, $file_path, $file_type) = explode(':', $attachment, 4);
                                            ?>
                                                <a href="<?= htmlspecialchars($file_path) ?>" target="_blank" class="attachment-link">
                                                    <?php
                                                    $icon = 'fas fa-file';
                                                    if (strpos($file_type, 'image') !== false) $icon = 'fas fa-file-image';
                                                    elseif (strpos($file_type, 'pdf') !== false) $icon = 'fas fa-file-pdf';
                                                    elseif (strpos($file_type, 'word') !== false) $icon = 'fas fa-file-word';
                                                    elseif (strpos($file_type, 'excel') !== false) $icon = 'fas fa-file-excel';
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
                    <?php if (isLoggedIn()): ?>
                        <a href="new_post.php" class="quick-link">
                            <i class="fas fa-edit"></i>
                            <span>新規投稿</span>
                        </a>
                        <a href="admin.php" class="quick-link">
                            <i class="fas fa-cogs"></i>
                            <span>管理画面</span>
                        </a>
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
        // 折りたたみ機能
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleNotices');
            const hiddenNotices = document.querySelectorAll('.notice-hidden');
            
            if (toggleBtn) {
                let isExpanded = false;
                
                toggleBtn.addEventListener('click', function() {
                    hiddenNotices.forEach(notice => {
                        if (isExpanded) {
                            notice.classList.add('notice-hidden');
                        } else {
                            notice.classList.remove('notice-hidden');
                        }
                    });
                    
                    const icon = toggleBtn.querySelector('i');
                    const text = toggleBtn.querySelector('span');
                    
                    if (isExpanded) {
                        icon.className = 'fas fa-chevron-down';
                        text.textContent = `さらに表示する (${hiddenNotices.length}件)`;
                    } else {
                        icon.className = 'fas fa-chevron-up';
                        text.textContent = '折りたたむ';
                    }
                    
                    isExpanded = !isExpanded;
                });
            }
        });

        // フィルター機能
        let currentFilter = 'all';
        
        function filterNotices(importance) {
            currentFilter = importance;
            const allNotices = document.querySelectorAll('.notice-item');
            const toggleContainer = document.querySelector('.toggle-container');
            
            let visibleCount = 0;
            let hiddenCount = 0;
            
            allNotices.forEach((notice, index) => {
                const noticeImportance = notice.dataset.importance;
                const shouldShow = importance === 'all' || noticeImportance === importance;
                
                if (shouldShow) {
                    notice.style.display = '';
                    if (visibleCount < 10) {
                        notice.classList.remove('notice-hidden');
                    } else {
                        notice.classList.add('notice-hidden');
                        hiddenCount++;
                    }
                    visibleCount++;
                } else {
                    notice.style.display = 'none';
                    notice.classList.remove('notice-hidden');
                }
            });
            
            // 折りたたみボタンの表示制御
            if (toggleContainer) {
                if (hiddenCount > 0) {
                    toggleContainer.style.display = 'block';
                    const text = toggleContainer.querySelector('span');
                    if (text) {
                        text.textContent = `さらに表示する (${hiddenCount}件)`;
                    }
                } else {
                    toggleContainer.style.display = 'none';
                }
            }
            
            // フィルター状態の視覚的フィードバック
            updateFilterUI(importance);
            
            // フィルター結果の表示
            showFilterResult(importance, visibleCount);
        }
        
        function updateFilterUI(activeFilter) {
            const statItems = document.querySelectorAll('.stat-item');
            statItems.forEach(item => {
                item.classList.remove('stat-active');
            });
            
            if (activeFilter !== 'all') {
                const activeItem = document.querySelector(`.stat-item.${activeFilter}`);
                if (activeItem) {
                    activeItem.classList.add('stat-active');
                }
            }
        }
        
        function showFilterResult(importance, count) {
            // 既存の結果表示を削除
            const existingResult = document.querySelector('.filter-result');
            if (existingResult) {
                existingResult.remove();
            }
            
            // フィルター結果を表示
            const pageHeader = document.querySelector('.page-header');
            const resultDiv = document.createElement('div');
            resultDiv.className = 'filter-result';
            resultDiv.style.cssText = `
                background: rgba(102, 126, 234, 0.1);
                border: 1px solid rgba(102, 126, 234, 0.3);
                border-radius: 6px;
                padding: 10px 15px;
                margin-top: 10px;
                font-size: 12px;
                color: #667eea;
                display: flex;
                align-items: center;
                justify-content: space-between;
            `;
            
            const importanceLabels = {
                'all': 'すべての投稿',
                'important': '重要な投稿',
                'notice': '周知投稿',
                'contact': '連絡投稿'
            };
            
            resultDiv.innerHTML = `
                <span>
                    <i class="fas fa-filter"></i>
                    ${importanceLabels[importance]} を表示中 (${count}件)
                </span>
                ${importance !== 'all' ? '<button onclick="filterNotices(\'all\')" style="background: none; border: none; color: #667eea; cursor: pointer; font-size: 11px;"><i class="fas fa-times"></i> クリア</button>' : ''}
            `;
            
            pageHeader.appendChild(resultDiv);
        }
    </script>
</body>
</html>
