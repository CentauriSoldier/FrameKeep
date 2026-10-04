<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/php/kickstart.php';

function placeholderThumbnail() {
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 150 150"><rect width="150" height="150" fill="#11131c"/><path d="M48 48h54v54H48zM60 48v54M90 48v54M48 62h12m-12 26h12m30-26h12M90 88h12" fill="none" stroke="#0dcaf0" stroke-width="3"/></svg>';
}

$id = (string) ($_GET['id'] ?? '');
$query = $db->prepare('SELECT videos.path, videos.thumbnail_key, videos.thumbnail_offset FROM videos JOIN libraries ON libraries.id = videos.library_id WHERE videos.id = ? AND libraries.enabled = 1');
$query->execute(array($id));
$video = $query->fetch(PDO::FETCH_ASSOC);
$path = $video['path'] ?? false;
$query->closeCursor();
$query = null;

if ($path === false || !is_file($path) || !is_readable($path)) {
    $db = null;
    placeholderThumbnail();
    exit;
}

$offset = (int) $db->query('SELECT thumbnail_time FROM app_settings WHERE id = 1')->fetchColumn();
$key = thumbnailKey($path, filemtime($path), $offset);
if (($video['thumbnail_key'] ?? null) !== $key || (int) ($video['thumbnail_offset'] ?? -1) !== $offset) {
    $query = $db->prepare('UPDATE videos SET thumbnail_key = ?, thumbnail_offset = ? WHERE id = ?');
    $query->execute(array($key, $offset, $id));
    $query = null;
}
$db = null;

$generationLock = fopen(F_THUMBNAIL_LOCK, 'c');
if (!$generationLock || !flock($generationLock, LOCK_SH | LOCK_NB)) {
    placeholderThumbnail();
    exit;
}

if (!is_dir(D_THUMBNAILS)) mkdir(D_THUMBNAILS, 0775, true);
$cache = D_THUMBNAILS . '/' . $key . '.jpg';
$lock = fopen($cache . '.lock', 'c');

if ($lock && flock($lock, LOCK_EX)) {
    if (!is_file($cache) && function_exists('proc_open')) {
        $temporary = $cache . '.tmp.jpg';
        $pipes = array();
        $job = D_THUMBNAILS . '/' . $key . '.job.json';
        file_put_contents($job, json_encode(array('current' => basename(str_replace('\\', '/', $path)), 'updated' => microtime(true)), JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
        try {
            $deadline = microtime(true) + 12;
            foreach (array_unique(array($offset, 0)) as $seek) {
                if (microtime(true) >= $deadline) break;
                if (is_file($temporary)) unlink($temporary);
                $process = proc_open(array(F_FFMPEG, '-nostdin', '-loglevel', 'error', '-y', '-ss', (string) $seek, '-i', $path, '-frames:v', '1', '-vf', 'scale=300:300:force_original_aspect_ratio=decrease', $temporary), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
                if (!is_resource($process)) break;
                fclose($pipes[0]);
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                do {
                    stream_get_contents($pipes[1]);
                    stream_get_contents($pipes[2]);
                    $status = proc_get_status($process);
                    if (!$status['running']) break;
                    usleep(50000);
                } while (microtime(true) < $deadline);
                if ($status['running']) proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                clearstatcache(true, $temporary);
                if (is_file($temporary) && filesize($temporary) > 0) {
                    rename($temporary, $cache);
                    break;
                }
            }
        } finally {
            if (is_file($job)) unlink($job);
        }
    }
    flock($lock, LOCK_UN);
    fclose($lock);
}

if (is_file($cache)) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    readfile($cache);
} else {
    placeholderThumbnail();
}
