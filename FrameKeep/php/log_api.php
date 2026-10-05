<?php

require_once __DIR__ . '/log.php';
header('Cache-Control: no-store');
$path = $_SERVER['DOCUMENT_ROOT'] . '/data/log.jsonl';
try {
    $action = $_GET['action'] ?? 'view';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        $action = $input['action'] ?? '';
        if ($action === 'append') {
            logEvent('Browser', substr((string) ($input['message'] ?? ''), 0, 8000), array('detail' => substr((string) ($input['detail'] ?? ''), 0, 8000)));
        } elseif ($action === 'clear') {
            if (is_file($path) && file_put_contents($path, '', LOCK_EX) === false) throw new RuntimeException('Could not clear the log.');
        } else throw new InvalidArgumentException('Unknown log action.');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => true));
        exit;
    }
    if (!in_array($action, array('view', 'download'), true)) throw new InvalidArgumentException('Unknown log action.');
    $entries = array();
    if (is_file($path)) {
        $file = fopen($path, 'rb');
        if (!$file || !flock($file, LOCK_SH)) throw new RuntimeException('Could not read the log.');
        try {
            while (($line = fgets($file)) !== false) {
                $entry = json_decode($line, true);
                if (is_array($entry)) $entries[] = $entry;
            }
        } finally { flock($file, LOCK_UN); fclose($file); }
    }
    if ($action === 'download') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="framekeep-log.txt"');
        foreach ($entries as $entry) echo $entry['time'] . ' [' . $entry['source'] . '] ' . $entry['message'] . PHP_EOL . json_encode($entry['context'], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL . PHP_EOL;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('entries' => array_reverse($entries)), JSON_INVALID_UTF8_SUBSTITUTE);
    }
} catch (Throwable $error) {
    logEvent('Log viewer', $error->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => $error->getMessage()), JSON_INVALID_UTF8_SUBSTITUTE);
}
