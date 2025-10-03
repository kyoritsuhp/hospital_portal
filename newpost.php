<?php
// ファイル名称: new_post.php
// 生成日時: 2025-09-26

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
                INSERT INTO notices (title, content, importance, display_start, display_end, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title,
                $content,
                $importance,
                empty($display_start) ? null : $display_start,
                empty($display_end) ? null : $display_end,
                $_SESSION['user_id']
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
        <!-- ヘッダー -->
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
                                複数ファイルの選択が可能です（各ファイル10MB以下）
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
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-paper-plane"></i> 投稿する
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- プレビューモーダル -->
    <div id="previewModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> 投稿プレビュー</h3>
                <button type="button" class="close-modal">&times;</button>
            </div>
            <div id="previewContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal">閉じる</button>
            </div>
        </div>
    </div>

    <style>
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .file-preview-item {
            display: inline-block;
            background: #f8f9fa;
            padding: 5px 10px;
            margin: 2px;
            border-radius: 15px;
            font-size: 11px;
            border: 1px solid #e9ecef;
        }
        
        .file-preview-item i {
            margin-right: 5px;
        }
        
        .preview-notice {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        
        .preview-title {
            font-size: 15px;
            font-weight: 600;
            color: #212529;
            margin-bottom: 8px;
        }
        
        .preview-content {
            color: #495057;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        
        .preview-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('attachments');
            const filePreview = document.getElementById('file-preview');
            const previewBtn = document.getElementById('previewBtn');
            const previewModal = document.getElementById('previewModal');
            const previewContent = document.getElementById('previewContent');
            const closeModalBtns = document.querySelectorAll('.close-modal');
            const submitBtn = document.getElementById('submitBtn');
            const postForm = document.getElementById('postForm');
            
            // フォーム送信処理
            postForm.addEventListener('submit', function(e) {
                // バリデーション
                const title = document.getElementById('title').value.trim();
                const content = document.getElementById('content').value.trim();
                
                if (!title || !content) {
                    e.preventDefault();
                    alert('タイトルと内容は必須項目です。');
                    return;
                }
                
                // 送信中の表示
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 投稿中...';
                
                // 他のボタンも無効化
                previewBtn.disabled = true;
                const cancelLink = document.querySelector('a[href="index.php"]');
                if (cancelLink) {
                    cancelLink.style.pointerEvents = 'none';
                    cancelLink.style.opacity = '0.5';
                }
            });
            
            // ファイル選択時のプレビュー
            fileInput.addEventListener('change', function() {
                filePreview.innerHTML = '';
                const files = Array.from(this.files);
                
                if (files.length === 0) return;
                
                files.forEach(file => {
                    // ファイルサイズチェック（10MB制限）
                    if (file.size > 10 * 1024 * 1024) {
                        alert(`ファイル "${file.name}" のサイズが大きすぎます。10MB以下のファイルを選択してください。`);
                        this.value = '';
                        filePreview.innerHTML = '';
                        return;
                    }
                    
                    const item = document.createElement('div');
                    item.className = 'file-preview-item';
                    
                    let icon = 'fas fa-file';
                    if (file.type.startsWith('image/')) icon = 'fas fa-file-image';
                    else if (file.type === 'application/pdf') icon = 'fas fa-file-pdf';
                    else if (file.type.includes('word')) icon = 'fas fa-file-word';
                    else if (file.type.includes('excel') || file.type.includes('sheet')) icon = 'fas fa-file-excel';
                    
                    item.innerHTML = `<i class="${icon}"></i>${file.name} (${formatFileSize(file.size)})`;
                    filePreview.appendChild(item);
                });
            });
            
            // プレビューボタン
            previewBtn.addEventListener('click', function() {
                const title = document.getElementById('title').value;
                const content = document.getElementById('content').value;
                const importance = document.querySelector('input[name="importance"]:checked').value;
                const displayStart = document.getElementById('display_start').value;
                const displayEnd = document.getElementById('display_end').value;
                
                if (!title || !content) {
                    alert('タイトルと内容を入力してください。');
                    return;
                }
                
                const importanceColors = {
                    'important': '#ff4444',
                    'notice': '#ffcc00',
                    'contact': '#4444ff'
                };
                
                const importanceLabels = {
                    'important': '重要',
                    'notice': '周知',
                    'contact': '連絡'
                };
                
                const now = new Date();
                const formatDate = now.getFullYear() + '/' + 
                    String(now.getMonth() + 1).padStart(2, '0') + '/' + 
                    String(now.getDate()).padStart(2, '0') + ' ' +
                    String(now.getHours()).padStart(2, '0') + ':' + 
                    String(now.getMinutes()).padStart(2, '0');
                
                let displayInfo = '';
                if (displayStart || displayEnd) {
                    displayInfo = '<div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 11px; color: #6c757d;">';
                    displayInfo += '<i class="fas fa-calendar"></i> 表示期間: ';
                    displayInfo += (displayStart || '開始日未設定') + ' 〜 ' + (displayEnd || '終了日未設定');
                    displayInfo += '</div>';
                }
                
                // 添付ファイルのプレビュー
                let attachmentInfo = '';
                const files = fileInput.files;
                if (files && files.length > 0) {
                    attachmentInfo = '<div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px;">';
                    attachmentInfo += '<h4 style="font-size: 12px; margin-bottom: 8px;"><i class="fas fa-paperclip"></i> 添付ファイル</h4>';
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        let icon = 'fas fa-file';
                        if (file.type.startsWith('image/')) icon = 'fas fa-file-image';
                        else if (file.type === 'application/pdf') icon = 'fas fa-file-pdf';
                        else if (file.type.includes('word')) icon = 'fas fa-file-word';
                        else if (file.type.includes('excel') || file.type.includes('sheet')) icon = 'fas fa-file-excel';
                        
                        attachmentInfo += `<div style="display: inline-block; margin: 2px 5px 2px 0; padding: 4px 8px; background: white; border-radius: 12px; font-size: 11px;">`;
                        attachmentInfo += `<i class="${icon}"></i> ${file.name} (${formatFileSize(file.size)})`;
                        attachmentInfo += '</div>';
                    }
                    attachmentInfo += '</div>';
                }
                
                previewContent.innerHTML = `
                    <div class="preview-notice" style="border-left-color: ${importanceColors[importance]}">
                        <div class="preview-meta">
                            <span class="importance-badge" style="background-color: ${importanceColors[importance]}; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                                ${importanceLabels[importance]}
                            </span>
                            <span style="font-size: 11px; color: #6c757d;">
                                <i class="fas fa-clock"></i> ${formatDate}
                            </span>
                            <span style="font-size: 11px; color: #6c757d;">
                                <i class="fas fa-user"></i> ${document.querySelector('.welcome').textContent.replace('ようこそ、', '').replace('さん', '')}
                            </span>
                        </div>
                        <h3 class="preview-title">${escapeHtml(title)}</h3>
                        <div class="preview-content">${escapeHtml(content).replace(/\n/g, '<br>')}</div>
                        ${displayInfo}
                        ${attachmentInfo}
                    </div>
                `;
                
                previewModal.classList.add('show');
            });
            
            // モーダルを閉じる
            closeModalBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    previewModal.classList.remove('show');
                });
            });
            
            // モーダル外クリックで閉じる
            previewModal.addEventListener('click', function(e) {
                if (e.target === previewModal) {
                    previewModal.classList.remove('show');
                }
            });
            
            // ユーティリティ関数
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        });
    </script>
</body>
</html>