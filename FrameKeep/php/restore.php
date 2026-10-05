<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$lock = null;
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/php/kickstart.php';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['confirmed'] ?? '') !== 'true') throw new InvalidArgumentException('Confirm database restoration first.');
    $upload = $_FILES['backup'] ?? null;
    if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) throw new InvalidArgumentException('Choose a database backup within the PHP upload size limit.');
    $file = fopen($upload['tmp_name'], 'rb');
    $signature = fread($file, 16);
    fclose($file);
    if ($signature !== "SQLite format 3\0") throw new InvalidArgumentException('That file is not a SQLite database backup.');
    $source = new PDO('sqlite:' . $upload['tmp_name']);
    $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $source->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $source->exec('PRAGMA query_only = ON');
    if ($source->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new InvalidArgumentException('The backup failed its database integrity check.');
    $available = $source->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    $required = array('videos', 'tags', 'video_tags', 'playlists', 'playlist_videos', 'playback');
    foreach ($required as $table) if (!in_array($table, $available, true)) throw new InvalidArgumentException('This is not a complete FrameKeep backup.');
    $tables = array('libraries', 'videos', 'tags', 'playlists', 'video_tags', 'playlist_videos', 'file_inventory', 'app_settings', 'playback');
    $records = array();
    foreach ($tables as $table) $records[$table] = in_array($table, $available, true) ? $source->query('SELECT * FROM ' . $table)->fetchAll() : array();
    $source = null;
    if (!in_array('libraries', $available, true)) {
        $records['libraries'] = array(array('id' => 1, 'name' => 'Primary library', 'path' => D_VIDEOS, 'enabled' => 1, 'status' => 'online'));
        foreach ($records['videos'] as &$video) {
            $video['library_id'] = 1;
            $video['path'] = $video['path'] ?? $video['id'];
            $video['filename'] = $video['filename'] ?? basename(str_replace('\\', '/', $video['path']));
        }
        unset($video);
        foreach ($records['file_inventory'] as &$entry) $entry['library_id'] = 1;
        unset($entry);
    }
    if (!$records['app_settings']) $records['app_settings'] = array(array('id' => 1));
    if (count($records['playback']) !== 1 || (int) $records['playback'][0]['id'] !== 1) throw new InvalidArgumentException('The backup contains invalid playback settings.');
    foreach ($records['playback'] as &$playback) {
        $playback['session'] = '';
        $playback['save_sequence'] = 0;
        $playback['audio_sequence'] = 0;
        $playback['autoplay_sequence'] = 0;
    }
    unset($playback);
    $lock = fopen(F_SCAN_LOCK, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('A scan or library operation is running. Try restoring afterward.');
    if (!is_dir(D_BACKUPS) && !mkdir(D_BACKUPS, 0775, true)) throw new RuntimeException('Could not create the recovery backup folder.');
    $recovery = D_BACKUPS . '/before-restore-' . date('Y-m-d-His') . '-' . bin2hex(random_bytes(4)) . '.db';
    $db->exec('VACUUM INTO ' . $db->quote($recovery));
    $db->beginTransaction();
    $db->exec('UPDATE playback SET save_sequence = save_sequence WHERE id = 1');
    foreach (array_reverse($tables) as $table) $db->exec('DELETE FROM ' . $table);
    foreach ($tables as $table) {
        $columns = array_column($db->query('PRAGMA table_info(' . $table . ')')->fetchAll(), 'name');
        foreach ($records[$table] as $row) {
            $row = array_intersect_key($row, array_flip($columns));
            if (!$row) throw new InvalidArgumentException('The backup contains an invalid ' . $table . ' record.');
            $query = $db->prepare('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')');
            $query->execute(array_values($row));
        }
    }
    if ($db->query('PRAGMA foreign_key_check')->fetchAll()) throw new InvalidArgumentException('The backup contains inconsistent library relationships.');
    $db->commit();
    echo json_encode(array('restored' => true, 'recovery' => webPath($recovery)), JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    logEvent('Database restore', $error->getMessage());
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code($error instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode(array('error' => $error->getMessage()), JSON_INVALID_UTF8_SUBSTITUTE);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
