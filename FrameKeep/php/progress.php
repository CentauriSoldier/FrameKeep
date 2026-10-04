<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
require_once F_FUNCTIONS;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$scan = readProgressFile(F_PROGRESS);
$scanLock = @fopen(F_SCAN_LOCK, 'c');
$running = false;
if ($scanLock) {
    $available = flock($scanLock, LOCK_EX | LOCK_NB);
    $running = !$available;
    if ($available) flock($scanLock, LOCK_UN);
    fclose($scanLock);
}
if ($scan) $scan['running'] = $running && !in_array($scan['phase'], array('complete', 'failed'), true);
$cache = array();
foreach (glob(D_THUMBNAILS . '/*.jpg') ?: array() as $path) if (preg_match('/^[a-f0-9]{64}\.jpg$/', basename($path))) $cache[basename($path, '.jpg')] = true;
$jobs = array();
foreach (glob(D_THUMBNAILS . '/*.job.json') ?: array() as $path) {
    $job = readProgressFile($path);
    if ($job && microtime(true) - ($job['updated'] ?? 0) < 20) $jobs[] = $job;
}
$thumbnails = array('cached' => count($cache), 'ready' => 0, 'pending' => 0, 'unchecked' => 0, 'generating' => count($jobs), 'current' => $jobs[0]['current'] ?? '', 'counted' => false);
try {
    if (is_file(F_DATA)) {
        $db = new PDO('sqlite:' . F_DATA);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA busy_timeout = 200');
        $db->exec('PRAGMA query_only = ON');
        $offset = (int) $db->query('SELECT thumbnail_time FROM app_settings WHERE id = 1')->fetchColumn();
        foreach ($db->query("SELECT thumbnail_key, thumbnail_offset FROM videos JOIN libraries ON libraries.id = videos.library_id WHERE videos.missing = 0 AND libraries.enabled = 1 AND libraries.status = 'online'")->fetchAll(PDO::FETCH_ASSOC) as $video) {
            $key = $video['thumbnail_key'];
            if ($key === null) $thumbnails['unchecked']++;
            elseif ((int) $video['thumbnail_offset'] === $offset && isset($cache[$key])) $thumbnails['ready']++;
            else $thumbnails['pending']++;
        }
        $thumbnails['counted'] = true;
    }
} catch (Throwable $error) {
    // Keep activity available while the database is briefly busy or migrating.
}
$db = null;
echo json_encode(array('scan' => $scan, 'thumbnails' => $thumbnails), JSON_INVALID_UTF8_SUBSTITUTE);
