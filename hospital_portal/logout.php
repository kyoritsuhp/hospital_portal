<?php
// ファイル名称: logout.php
// 生成日時: 2025-09-26

require_once 'config.php';

// セッションを破棄
session_destroy();

// トップページにリダイレクト
header('Location: index.php');
exit;
?>
