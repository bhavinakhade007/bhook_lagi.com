<?php
// Utilities for AI endpoints: logging helper
function ai_log($level, $context, $data = null) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $path = $logDir . '/ai_debug.log';
    $time = gmdate('Y-m-d H:i:s');
    $entry = [
        'time' => $time,
        'level' => $level,
        'context' => $context,
        'data' => $data,
    ];
    $json = json_encode($entry, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    // Truncate overly long entries
    if (strlen($json) > 8000) {
        $json = substr($json, 0, 8000) . '...';
    }
    // append
    @file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}

?>