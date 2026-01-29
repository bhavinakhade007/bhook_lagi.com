<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php'); exit();
}
$log = __DIR__ . '/logs/ai_debug.log';
$lines = [];
if (file_exists($log)) {
    $content = file_get_contents($log);
    $lines = array_filter(array_map('trim', explode(PHP_EOL, $content)));
    $lines = array_slice($lines, -500); // last 500 entries
}
?><!doctype html>
<html>
<head><meta charset="utf-8"><title>AI Logs</title>
<style>body{font-family:Segoe UI,Arial;background:#f6f6f6;padding:18px}pre{background:#111;color:#0f0;padding:12px;border-radius:8px;overflow:auto;max-height:80vh}</style>
</head>
<body>
<h2>AI Debug Log (last <?php echo count($lines); ?> entries)</h2>
<p><a href="download_ai_log.php">Download full log</a></p>
<pre><?php foreach($lines as $l) echo htmlspecialchars($l) . "\n"; ?></pre>
</body>
</html>