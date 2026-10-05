<?php

function logEvent($source, $message, $context = array()) {
    static $writing = false;
    if ($writing) return;
    $writing = true;
    try {
        $folder = $_SERVER['DOCUMENT_ROOT'] . '/data';
        if (!is_dir($folder)) @mkdir($folder, 0775, true);
        $entry = json_encode(array('time' => gmdate('c'), 'source' => $source, 'message' => (string) $message, 'context' => $context), JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        if ($entry !== false) @file_put_contents($folder . '/log.jsonl', $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    } finally { $writing = false; }
}

set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) logEvent('PHP', $message, array('file' => $file, 'line' => $line));
    return false;
});
set_exception_handler(function ($error) {
    logEvent('PHP exception', $error->getMessage(), array('file' => $error->getFile(), 'line' => $error->getLine()));
    http_response_code(500);
    echo 'FrameKeep could not complete this request. See the error log.';
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) logEvent('PHP fatal error', $error['message'], array('file' => $error['file'], 'line' => $error['line']));
});
