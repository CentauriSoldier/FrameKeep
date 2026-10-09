<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/php/kickstart.php';

$directory = D_DATA . '/downloads';
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Could not create the download folder.');

function downloadFailure($message, $code = 400, $detail = array()) {
    logEvent('Download', $message, $detail);
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array('error' => $message), JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $format = $input['format'] ?? '';
    if (!in_array($format, array('video', 'mp3', 'ogg'), true)) downloadFailure('Choose video, MP3, or OGG.');
    $query = $db->prepare('SELECT videos.path FROM videos JOIN libraries ON libraries.id = videos.library_id WHERE videos.id = ? AND videos.missing = 0 AND libraries.enabled = 1');
    $query->execute(array((string) ($input['id'] ?? '')));
    $path = $query->fetchColumn();
    $query = null;
    $db = null;
    if (!$path || !is_file($path) || !is_readable($path)) downloadFailure('The video file is unavailable.', 404);
    // Only generated files with our token names are eligible for expiry cleanup.
    foreach (glob($directory . '/*') ?: array() as $expired) {
        if (preg_match('/^[a-f0-9]{48}\.(json|mp3|ogg)$/', basename($expired)) && filemtime($expired) < time() - 86400) unlink($expired);
    }
    $token = bin2hex(random_bytes(24));
    $name = basename(str_replace('\\', '/', $path));
    $output = $path;
    if ($format !== 'video') {
        if (!function_exists('proc_open') || F_FFMPEG === '') downloadFailure('FFmpeg is not configured for audio downloads.', 503);
        set_time_limit(0);
        $lock = fopen($directory . '/conversion.lock', 'c');
        if (!$lock) downloadFailure('Could not open the audio conversion lock.', 503);
        $deadline = microtime(true) + 1200;
        while (!flock($lock, LOCK_EX | LOCK_NB)) {
            if (connection_aborted() || microtime(true) >= $deadline) {
                fclose($lock);
                downloadFailure('Audio conversion is busy. Please try again.', 503);
            }
            usleep(100000);
        }
        $output = $directory . '/' . $token . '.' . $format;
        $process = null;
        $pipes = array();
        try {
            $codec = $format === 'mp3' ? 'libmp3lame' : 'libvorbis';
            $quality = $format === 'mp3' ? '2' : '6';
            $process = proc_open(array(F_FFMPEG, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y', '-i', $path, '-map', '0:a:0', '-vn', '-c:a', $codec, '-q:a', $quality, $output), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
            if (!is_resource($process)) throw new RuntimeException('Could not start FFmpeg.');
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $diagnostic = '';
            do {
                stream_get_contents($pipes[1]);
                $diagnostic = substr($diagnostic . stream_get_contents($pipes[2]), -8000);
                $status = proc_get_status($process);
                if (!$status['running']) break;
                if (connection_aborted() || microtime(true) >= $deadline) throw new RuntimeException('Audio conversion was interrupted or exceeded its twenty-minute limit.');
                usleep(100000);
            } while (true);
            $diagnostic = substr($diagnostic . stream_get_contents($pipes[2]), -8000);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            $process = null;
            clearstatcache(true, $output);
            if (($status['exitcode'] !== 0 && $exit !== 0) || !is_file($output) || filesize($output) === 0) throw new RuntimeException('FFmpeg could not extract the audio. ' . trim($diagnostic));
        } catch (Throwable $error) {
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            if (is_file($output)) unlink($output);
            flock($lock, LOCK_UN);
            fclose($lock);
            downloadFailure($error->getMessage(), 500, array('path' => $path, 'format' => $format));
        }
        flock($lock, LOCK_UN);
        fclose($lock);
        $name = pathinfo($name, PATHINFO_FILENAME) . '.' . $format;
    }
    $metadata = array('path' => $output, 'name' => $name, 'temporary' => $format !== 'video', 'type' => $format === 'mp3' ? 'audio/mpeg' : ($format === 'ogg' ? 'audio/ogg' : 'application/octet-stream'));
    if (file_put_contents($directory . '/' . $token . '.json', json_encode($metadata, JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX) === false) {
        if ($format !== 'video') unlink($output);
        downloadFailure('Could not prepare the download.', 500);
    }
    header('Content-Type: application/json');
    echo json_encode(array('token' => $token));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') downloadFailure('Unsupported download request.', 405);
$token = (string) ($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{48}$/', $token)) downloadFailure('Invalid download token.');
$record = $directory . '/' . $token . '.json';
$metadata = is_file($record) ? json_decode(file_get_contents($record), true) : null;
if (!$metadata || !is_file($metadata['path']) || !is_readable($metadata['path'])) downloadFailure('The download is unavailable or expired.', 404);
$file = fopen($metadata['path'], 'rb');
if (!$file) downloadFailure('Could not open the download.', 404);
$db = null;
set_time_limit(0);
$name = preg_replace('/[\x00-\x1f\x7f]/', '', $metadata['name']);
$fallback = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name);
header('Content-Type: ' . $metadata['type']);
header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . fstat($file)['size']);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
while (ob_get_level()) ob_end_clean();
ignore_user_abort(true);
while (!feof($file) && !connection_aborted()) {
    echo fread($file, 1024 * 1024);
    flush();
}
fclose($file);
// Retain prepared downloads for retry links; expiry cleanup removes them after one day.

