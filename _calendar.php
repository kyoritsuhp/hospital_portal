<?php
// ファイル名称: calendar.php
// 生成日時: 2025-09-26

require_once 'config.php';

$message = '';
$error = '';

// カレンダー表示用の年月取得
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

// 月の範囲チェック
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

// AJAX処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'ログインが必要です']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $start_date = $_POST['start_date'] ?? '';
                $start_time = $_POST['start_time'] === '--:--' ? null : $_POST['start_time'];
                $end_time = $_POST['end_time'] === '--:--' ? null : $_POST['end_time'];
                $color = $_POST['color'] ?? '#3788d8';
                
                if (empty($title) || empty($start_date)) {
                    throw new Exception('予定名と日付は必須です');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO calendar_events (title, content, start_date, start_time, end_time, color, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $content, $start_date, $start_time, $end_time, $color, $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => '予定を追加しました']);
                break;
                
            case 'update':
                $event_id = $_POST['event_id'] ?? 0;
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $start_date = $_POST['start_date'] ?? '';
                $start_time = $_POST['start_time'] === '--:--' ? null : $_POST['start_time'];
                $end_time = $_POST['end_time'] === '--:--' ? null : $_POST['end_time'];
                $color = $_POST['color'] ?? '#3788d8';
                
                if (empty($title) || empty($start_date)) {
                    throw new Exception('予定名と日付は必須です');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE calendar_events 
                    SET title = ?, content = ?, start_date = ?, start_time = ?, end_time = ?, color = ?
                    WHERE id = ? AND created_by = ?
                ");
                $stmt->execute([$title, $content, $start_date, $start_time, $end_time, $color, $event_id, $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => '予定を更新しました']);
                break;
                
            case 'delete':
                $event_id = $_POST['event_id'] ?? 0;
                
                $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = ? AND created_by = ?");
                $stmt->execute([$event_id, $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => '予定を削除しました']);
                break;
                
            case 'move':
                $event_id = $_POST['event_id'] ?? 0;
                $new_date = $_POST['new_date'] ?? '';
                
                if (empty($new_date)) {
                    throw new Exception('移動先の日付が無効です');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE calendar_events 
                    SET start_date = ?
                    WHERE id = ? AND created_by = ?
                ");
                $stmt->execute([$new_date, $event_id, $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => '予定を移動しました']);
                break;
                
            case 'get_event':
                $event_id = $_POST['event_id'] ?? 0;
                
                $stmt = $pdo->prepare("SELECT * FROM calendar_events WHERE id = ?");
                $stmt->execute([$event_id]);
                $event = $stmt->fetch();
                
                if ($event) {
                    echo json_encode(['success' => true, 'event' => $event]);
                } else {
                    echo json_encode(['success' => false, 'message' => '予定が見つかりません']);
                }
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 指定月の予定取得
$first_day = $year . '-' . sprintf('%02d', $month) . '-01';
$last_day = $year . '-' . sprintf('%02d', $month) . '-' . date('t', strtotime($first_day));

$stmt = $pdo->prepare("
    SELECT e.*, u.username 
    FROM calendar_events e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.start_date BETWEEN ? AND ?
    ORDER BY e.start_date, e.start_time
");
$stmt->execute([$first_day, $last_day]);
$events = $stmt->fetchAll();

// 予定を日付別にグループ化
$events_by_date = [];
foreach ($events as $event) {
    $events_by_date[$event['start_date']][] = $event;
}

// カレンダー生成用データ
$first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day_of_month);
$first_day_of_week = date('w', $first_day_of_month); // 0=日曜日

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カレンダー - 院内ポータルサイト</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- ヘッダー -->
        <header class="header">
            <h1><i class="fas fa-hospital"></i> 院内ポータルサイト</h1>
            <div class="header-actions">
                <?php if (isLoggedIn()): ?>
                    <span class="welcome">ようこそ、<?= htmlspecialchars(getCurrentUser()['username']) ?>さん</span>
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
            <!-- サイドメニュー（左） -->
            <nav class="sidebar sidebar-left">
                <h3><i class="fas fa-bars"></i> メニュー</h3>
                <ul class="menu-list">
                    <li><a href="index.php"><i class="fas fa-home"></i> ホーム</a></li>
                    <li><a href="calendar.php" class="active"><i class="fas fa-calendar"></i> カレンダー</a></li>
                    <li><a href="#"><i class="fas fa-users"></i> スタッフ一覧</a></li>
                    <li><a href="#"><i class="fas fa-phone"></i> 内線番号</a></li>
                    <li><a href="#"><i class="fas fa-file-medical"></i> 診療情報</a></li>
                    <li><a href="#"><i class="fas fa-chart-bar"></i> 統計情報</a></li>
                </ul>
            </nav>

            <!-- メインコンテンツ -->
            <main class="content">
                <div class="calendar-container">
                    <!-- カレンダーヘッダー -->
                    <div class="calendar-header">
                        <div class="calendar-nav">
                            <button onclick="changeMonth(<?= $prev_year ?>, <?= $prev_month ?>)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h3><?= $year ?>年<?= $month ?>月</h3>
                            <button onclick="changeMonth(<?= $next_year ?>, <?= $next_month ?>)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <div>
                            <button onclick="changeMonth(<?= date('Y') ?>, <?= date('n') ?>)" class="btn btn-today">
                                <i class="fas fa-home"></i> 今月
                            </button>
                        </div>
                    </div>

                    <!-- カレンダーグリッド -->
                    <div class="calendar-grid">
                        <!-- 曜日ヘッダー -->
                        <div class="calendar-day-header">日</div>
                        <div class="calendar-day-header">月</div>
                        <div class="calendar-day-header">火</div>
                        <div class="calendar-day-header">水</div>
                        <div class="calendar-day-header">木</div>
                        <div class="calendar-day-header">金</div>
                        <div class="calendar-day-header">土</div>

                        <?php
                        // 前月の空白日
                        for ($i = 0; $i < $first_day_of_week; $i++) {
                            echo '<div class="calendar-day other-month"></div>';
                        }

                        // 当月の日付
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $is_today = $current_date === date('Y-m-d');
                            $day_events = $events_by_date[$current_date] ?? [];
                            
                            echo '<div class="calendar-day' . ($is_today ? ' today' : '') . '" 
                                       data-date="' . $current_date . '"
                                       onclick="' . (isLoggedIn() ? 'openAddEventModal(\'' . $current_date . '\')' : '') . '">';
                            echo '<div class="day-number">' . $day . '</div>';
                            
                            // 予定表示
                            foreach ($day_events as $event) {
                                $time_str = '';
                                if ($event['start_time']) {
                                    $time_str = date('H:i', strtotime($event['start_time']));
                                    if ($event['end_time']) {
                                        $time_str .= '-' . date('H:i', strtotime($event['end_time']));
                                    }
                                }
                                
                                echo '<div class="calendar-event" 
                                           style="background-color: ' . htmlspecialchars($event['color']) . '"
                                           onclick="event.stopPropagation(); openEventModal(' . $event['id'] . ')"
                                           draggable="' . (isLoggedIn() ? 'true' : 'false') . '"
                                           data-event-id="' . $event['id'] . '">';
                                
                                if ($time_str) {
                                    echo '<div class="calendar-event-time">' . $time_str . '</div>';
                                }
                                echo '<div>' . htmlspecialchars($event['title']) . '</div>';
                                echo '</div>';
                            }
                            
                            echo '</div>';
                        }

                        // 次月の空白日（7の倍数になるまで）
                        $total_cells = $first_day_of_week + $days_in_month;
                        $remaining_cells = 7 - ($total_cells % 7);
                        if ($remaining_cells < 7) {
                            for ($i = 0; $i < $remaining_cells; $i++) {
                                echo '<div class="calendar-day other-month"></div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </main>

            <!-- サイドメニュー（右） -->
            <aside class="sidebar sidebar-right">
                <h3><i class="fas fa-info-circle"></i> 操作方法</h3>
                <div class="sidebar-help-text">
                    <?php if (isLoggedIn()): ?>
                        <p><strong>予定の追加:</strong><br>
                        日付をクリックしてください</p>
                        
                        <p><strong>予定の編集:</strong><br>
                        予定をクリックしてください</p>
                        
                        <p><strong>予定の移動:</strong><br>
                        予定をドラッグして別の日に移動できます</p>
                    <?php else: ?>
                        <p><strong>閲覧モード:</strong><br>
                        ログインすると予定の追加・編集ができます</p>
                        
                        <p><strong>予定の確認:</strong><br>
                        予定をクリックすると詳細が表示されます</p>
                    <?php endif; ?>
                </div>

                <div class="sidebar-widget">
                    <h4>
                        <i class="fas fa-calendar-check"></i> 今月の予定数
                    </h4>
                    <div class="widget-content">
                        <?= count($events) ?>件
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- 予定追加モーダル -->
    <div id="addEventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> 予定追加</h3>
                <button type="button" class="close-modal">&times;</button>
            </div>
            <form id="addEventForm">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="start_date" id="add_start_date">
                
                <div class="form-group">
                    <label for="add_title">予定 <span style="color: #dc3545;">*</span></label>
                    <input type="text" id="add_title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="add_content">内容</label>
                    <textarea id="add_content" name="content" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="add_start_time">開始時間</label>
                        <input type="time" id="add_start_time" name="start_time" class="form-control" value="--:--">
                    </div>
                    <div class="form-group">
                        <label for="add_end_time">終了時間</label>
                        <input type="time" id="add_end_time" name="end_time" class="form-control" value="--:--">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="add_color">色</label>
                    <div class="color-picker-group">
                        <input type="color" id="add_color" name="color" class="form-control" value="#3788d8">
                        <select onchange="document.getElementById('add_color').value = this.value">
                            <option value="#3788d8">青</option>
                            <option value="#28a745">緑</option>
                            <option value="#dc3545">赤</option>
                            <option value="#ffc107">黄</option>
                            <option value="#6f42c1">紫</option>
                            <option value="#fd7e14">オレンジ</option>
                        </select>
                    </div>
                </div>
            </form>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal">キャンセル</button>
                <button type="button" class="btn btn-primary" onclick="saveEvent('add')">
                    <i class="fas fa-save"></i> 保存
                </button>
            </div>
        </div>
    </div>

    <!-- 予定表示・編集モーダル -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="eventModalTitle"><i class="fas fa-calendar"></i> 予定詳細</h3>
                <button type="button" class="close-modal">&times;</button>
            </div>
            <form id="editEventForm">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="event_id" id="edit_event_id">
                <input type="hidden" name="start_date" id="edit_start_date">
                
                <div class="form-group">
                    <label for="edit_title">予定 <span style="color: #dc3545;">*</span></label>
                    <input type="text" id="edit_title" name="title" class="form-control" 
                           <?= isLoggedIn() ? 'required' : 'readonly' ?>>
                </div>
                
                <div class="form-group">
                    <label for="edit_content">内容</label>
                    <textarea id="edit_content" name="content" class="form-control" rows="3"
                              <?= isLoggedIn() ? '' : 'readonly' ?>></textarea>
                </div>
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="edit_start_time">開始時間</label>
                        <input type="time" id="edit_start_time" name="start_time" class="form-control"
                               <?= isLoggedIn() ? '' : 'readonly' ?>>
                    </div>
                    <div class="form-group">
                        <label for="edit_end_time">終了時間</label>
                        <input type="time" id="edit_end_time" name="end_time" class="form-control"
                               <?= isLoggedIn() ? '' : 'readonly' ?>>
                    </div>
                </div>
                
                <?php if (isLoggedIn()): ?>
                <div class="form-group">
                    <label for="edit_color">色</label>
                    <div class="color-picker-group">
                        <input type="color" id="edit_color" name="color" class="form-control">
                        <select onchange="document.getElementById('edit_color').value = this.value">
                            <option value="#3788d8">青</option>
                            <option value="#28a745">緑</option>
                            <option value="#dc3545">赤</option>
                            <option value="#ffc107">黄</option>
                            <option value="#6f42c1">紫</option>
                            <option value="#fd7e14">オレンジ</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </form>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal">
                    <?= isLoggedIn() ? 'キャンセル' : '閉じる' ?>
                </button>
                <?php if (isLoggedIn()): ?>
                    <button type="button" class="btn btn-danger" onclick="deleteEvent()">
                        <i class="fas fa-trash"></i> 削除
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveEvent('edit')">
                        <i class="fas fa-save"></i> 更新
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        let draggedEvent = null;
        
        // 月変更
        function changeMonth(year, month) {
            window.location.href = `calendar.php?year=${year}&month=${month}`;
        }
        
        // 予定追加モーダルを開く
        function openAddEventModal(date) {
            <?php if (!isLoggedIn()): ?>
                return;
            <?php endif; ?>
            
            document.getElementById('add_start_date').value = date;
            document.getElementById('add_title').value = '';
            document.getElementById('add_content').value = '';
            document.getElementById('add_start_time').value = '';
            document.getElementById('add_end_time').value = '';
            document.getElementById('add_color').value = '#3788d8';
            
            // 時間の初期値を設定
            const timeInputs = ['add_start_time', 'add_end_time'];
            timeInputs.forEach(id => {
                const input = document.getElementById(id);
                input.placeholder = '--:--';
            });
            
            document.getElementById('addEventModal').classList.add('show');
        }
        
        // 予定表示・編集モーダルを開く
        function openEventModal(eventId) {
            fetch('calendar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=1&action=get_event&event_id=${eventId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const event = data.event;
                    document.getElementById('edit_event_id').value = event.id;
                    document.getElementById('edit_start_date').value = event.start_date;
                    document.getElementById('edit_title').value = event.title;
                    document.getElementById('edit_content').value = event.content || '';
                    document.getElementById('edit_start_time').value = event.start_time || '';
                    document.getElementById('edit_end_time').value = event.end_time || '';
                    
                    <?php if (isLoggedIn()): ?>
                    document.getElementById('edit_color').value = event.color || '#3788d8';
                    document.getElementById('eventModalTitle').innerHTML = '<i class="fas fa-edit"></i> 予定編集';
                    <?php else: ?>
                    document.getElementById('eventModalTitle').innerHTML = '<i class="fas fa-eye"></i> 予定詳細';
                    <?php endif; ?>
                    
                    document.getElementById('eventModal').classList.add('show');
                } else {
                    alert('予定の取得に失敗しました: ' + data.message);
                }
            })
            .catch(error => {
                alert('エラーが発生しました');
                console.error('Error:', error);
            });
        }
        
        // 予定保存
        function saveEvent(mode) {
            const form = mode === 'add' ? 'addEventForm' : 'editEventForm';
            const formData = new FormData(document.getElementById(form));
            
            // 時間が空の場合は--:--にする
            const startTime = formData.get('start_time');
            const endTime = formData.get('end_time');
            
            if (!startTime) formData.set('start_time', '--:--');
            if (!endTime) formData.set('end_time', '--:--');
            
            fetch('calendar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + data.message);
                }
            })
            .catch(error => {
                alert('通信エラーが発生しました');
                console.error('Error:', error);
            });
        }
        
        // 予定削除
        function deleteEvent() {
            if (!confirm('この予定を削除しますか？')) return;
            
            const eventId = document.getElementById('edit_event_id').value;
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'delete');
            formData.append('event_id', eventId);
            
            fetch('calendar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('削除に失敗しました: ' + data.message);
                }
            })
            .catch(error => {
                alert('通信エラーが発生しました');
                console.error('Error:', error);
            });
        }
        
        // ドラッグ&ドロップ機能
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isLoggedIn()): ?>
            // ドラッグ開始
            document.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('calendar-event')) {
                    draggedEvent = {
                        id: e.target.dataset.eventId,
                        element: e.target
                    };
                    e.dataTransfer.effectAllowed = 'move';
                }
            });
            
            // ドラッグオーバー
            document.addEventListener('dragover', function(e) {
                if (draggedEvent && e.target.classList.contains('calendar-day')) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    e.target.style.backgroundColor = 'rgba(102, 126, 234, 0.2)';
                }
            });
            
            // ドラッグリーブ
            document.addEventListener('dragleave', function(e) {
                if (e.target.classList.contains('calendar-day')) {
                    e.target.style.backgroundColor = '';
                }
            });
            
            // ドロップ
            document.addEventListener('drop', function(e) {
                if (draggedEvent && e.target.classList.contains('calendar-day')) {
                    e.preventDefault();
                    e.target.style.backgroundColor = '';
                    
                    const newDate = e.target.dataset.date;
                    if (newDate) {
                        const formData = new FormData();
                        formData.append('ajax', '1');
                        formData.append('action', 'move');
                        formData.append('event_id', draggedEvent.id);
                        formData.append('new_date', newDate);
                        
                        fetch('calendar.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('移動に失敗しました: ' + data.message);
                            }
                        })
                        .catch(error => {
                            alert('通信エラーが発生しました');
                            console.error('Error:', error);
                        });
                    }
                }
                
                draggedEvent = null;
            });
            <?php endif; ?>
            
            // モーダル制御
            const modals = document.querySelectorAll('.modal');
            const closeModalBtns = document.querySelectorAll('.close-modal');
            
            closeModalBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    modals.forEach(modal => modal.classList.remove('show'));
                });
            });
            
            modals.forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.classList.remove('show');
                    }
                });
            });
        });
    </script>
</body>
</html>

