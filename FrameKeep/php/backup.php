<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/php/kickstart.php';

$backup = null;
try {
    $backup = tempnam(D_DATA, 'backup-');
    if ($backup === false) throw new RuntimeException('Could not create a database backup.');
    unlink($backup);
    $db->exec('VACUUM INTO ' . $db->quote($backup));
    $db = null;
    header('Content-Type: application/vnd.sqlite3');
    header('Content-Disposition: attachment; filename="FrameKeep-' . date('Y-m-d-His') . '.db"');
    header('Content-Length: ' . filesize($backup));
    header('Cache-Control: no-store');
    readfile($backup);
} catch (Throwable $error) {
    logEvent('Database backup', $error->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Database backup failed: ' . $error->getMessage();
} finally {
    if ($backup && is_file($backup)) unlink($backup);
}
