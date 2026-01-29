<?php
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
$log = __DIR__ . '/logs/ai_debug.log';
if (!file_exists($log)) { http_response_code(404); echo 'Not found'; exit(); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="ai_debug.log"');
readfile($log);
exit();
?>