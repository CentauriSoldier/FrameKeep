<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/php/kickstart.php';

$query = $db->prepare('SELECT videos.path FROM videos JOIN libraries ON libraries.id = videos.library_id WHERE videos.id = ? AND libraries.enabled = 1');
$query->execute(array((string) ($_GET['id'] ?? '')));
$path = $query->fetchColumn();

if ($path === false || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Video file is unavailable.');
}

$file = fopen($path, 'rb');

if ($file === false) {
    http_response_code(404);
    exit('Video file could not be opened.');
}

$size = fstat($file)['size'];
$start = 0;
$end = $size - 1;
$types = array('mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg', 'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska', 'avi' => 'video/x-msvideo');

header('Content-Type: ' . ($types[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];

    if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $match) || ($match[1] === '' && $match[2] === '') || $size === 0) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        fclose($file);
        exit;
    }

    if ($match[1] === '') {
        $start = max(0, $size - (int) $match[2]);
    } else {
        $start = (int) $match[1];
    }

    if ($match[1] !== '' && $match[2] !== '') {
        $end = min($end, (int) $match[2]);
    }

    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        fclose($file);
        exit;
    }

    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}

header('Content-Length: ' . max(0, $end - $start + 1));

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    fclose($file);
    exit;
}

$query = null;
$db = null;
set_time_limit(0);

while (ob_get_level()) {
    ob_end_clean();
}

fseek($file, $start);
$remaining = $end - $start + 1;

while ($remaining > 0 && !feof($file) && !connection_aborted()) {
    $chunk = fread($file, min(1048576, $remaining));

    if ($chunk === false || $chunk === '') {
        break;
    }

    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}

fclose($file);
