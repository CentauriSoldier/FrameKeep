<?php

function webPath($file) {
    return str_replace('\\', '/', ltrim(substr($file, strlen(D_ROOT)), '/\\'));
}

function libraryLocations($db) {
    $libraries = $db->query('SELECT id, name, path, enabled, status FROM libraries ORDER BY id')->fetchAll();
    foreach ($libraries as &$library) {
        $library['id'] = (int) $library['id'];
        if (!$library['enabled']) {
            $library['status'] = 'disabled';
        } else {
            clearstatcache(true, $library['path']);
            if (!is_dir($library['path']) || !is_readable($library['path']) || @scandir($library['path']) === false) $library['status'] = 'offline';
            elseif ($library['status'] === 'offline' || $library['status'] === 'disabled') $library['status'] = 'online';
        }
    }
    unset($library);
    return $libraries;
}

function libraryState($db) {
    $libraries = libraryLocations($db);
    $locations = array_column($libraries, null, 'id');
    $videos = $db->query('SELECT id, name, path, filename, missing, file_size, library_id, rating, duration, video_width, video_height, media_checked, thumbnail_custom_time FROM videos ORDER BY name COLLATE NOCASE, id')->fetchAll();
    $tags = $db->query('SELECT id, name, text_color, background_color, font, font_size FROM tags ORDER BY name COLLATE NOCASE, id')->fetchAll();
    $playlists = $db->query('SELECT id, name, text_color, background_color, font, font_size, cover_video FROM playlists ORDER BY name COLLATE NOCASE, id')->fetchAll();
    $assignments = array();

    foreach ($db->query('SELECT video_id, tag_id FROM video_tags') as $assignment) {
        $assignments[$assignment['video_id']][] = (int) $assignment['tag_id'];
    }

    foreach ($videos as &$video) {
        $video['thumbnail_ready'] = false;
        $video['library_status'] = $locations[$video['library_id']]['status'] ?? 'offline';
        $video['library_name'] = $locations[$video['library_id']]['name'] ?? 'Unknown library';
        $video['tags'] = $assignments[$video['id']] ?? array();
        $video['candidates'] = array();
        if ($video['missing'] && $video['library_status'] === 'online') {
            $query = $db->prepare("SELECT path, filename, file_size, library_id FROM file_inventory WHERE library_id IN (SELECT id FROM libraries WHERE enabled = 1 AND status = 'online') AND (filename = ? OR (? IS NOT NULL AND file_size = ?)) AND path NOT IN (SELECT path FROM videos) ORDER BY filename = ? DESC, path");
            $query->execute(array($video['filename'], $video['file_size'], $video['file_size'], $video['filename']));
            $video['candidates'] = array_values(array_filter($query->fetchAll(), function ($candidate) use ($locations) { return ($locations[$candidate['library_id']]['status'] ?? 'offline') === 'online'; }));
        }
    }

    unset($video);
    $offset = (int) $db->query('SELECT thumbnail_time FROM app_settings WHERE id = 1')->fetchColumn();
    foreach ($videos as &$video) {
        if (!is_file($video['path']) || !is_readable($video['path'])) continue;
        $key = thumbnailKey($video['path'], filemtime($video['path']), $video['thumbnail_custom_time'] ?? $offset);
        $video['thumbnail_ready'] = is_file(D_THUMBNAILS . '/' . $key . '.jpg');
    }
    unset($video);
    $items = array();

    foreach ($db->query('SELECT id, playlist_id, video_id, position FROM playlist_videos ORDER BY position, id') as $item) {
        $item['id'] = (int) $item['id'];
        $item['position'] = (int) $item['position'];
        $items[$item['playlist_id']][] = $item;
    }

    foreach ($playlists as &$playlist) {
        $playlist['id'] = (int) $playlist['id'];
        $playlist['items'] = $items[$playlist['id']] ?? array();
    }

    unset($playlist);

    return array('untagged' => json_decode($db->query('SELECT untagged_style FROM app_settings WHERE id = 1')->fetchColumn(), true) ?: array(), 'libraries' => $libraries, 'settings' => $db->query('SELECT scan_on_start, auto_orphan, auto_scan, scan_frequency, thumbnail_size, thumbnail_time, thumbnail_version, scroll_to_player, page_size, fill_last_row FROM app_settings WHERE id = 1')->fetch(), 'videos' => $videos, 'tags' => $tags, 'playlists' => $playlists, 'playback' => $db->query('SELECT video_id, position, autoplay, queue, queue_index, volume, muted FROM playback WHERE id = 1')->fetch());
}

function requiredName($value) {
    $value = trim((string) $value);

    if ($value === '') {
        throw new InvalidArgumentException('Enter a name.');
    }

    return $value;
}

function videoDetails($db, $id) {
    $query = $db->prepare('SELECT id, name, path, filename, file_size, rating, duration, video_width, video_height FROM videos WHERE id = ?');
    $query->execute(array($id));
    $video = $query->fetch();
    if (!$video) throw new InvalidArgumentException('That video is no longer in the library.');
    clearstatcache(true, $video['path']);
    $stat = @stat($video['path']);
    $video['available'] = $stat !== false && is_file($video['path']);
    $video['size_cached'] = !$video['available'];
    if ($video['available']) $video['file_size'] = $stat['size'];
    $video['created'] = $stat && PHP_OS_FAMILY === 'Windows' ? $stat['ctime'] : null;
    $video['modified'] = $stat ? $stat['mtime'] : null;
    $video['metadata_changed'] = $stat && PHP_OS_FAMILY !== 'Windows' ? $stat['ctime'] : null;
    $query = $db->prepare('SELECT tags.name FROM tags JOIN video_tags ON tags.id = video_tags.tag_id WHERE video_tags.video_id = ? ORDER BY tags.name COLLATE NOCASE');
    $query->execute(array($id));
    $video['tags'] = $query->fetchAll(PDO::FETCH_COLUMN);
    $query = $db->prepare('SELECT DISTINCT playlists.name FROM playlists JOIN playlist_videos ON playlists.id = playlist_videos.playlist_id WHERE playlist_videos.video_id = ? ORDER BY playlists.name COLLATE NOCASE');
    $query->execute(array($id));
    $video['playlists'] = $query->fetchAll(PDO::FETCH_COLUMN);
    return $video;
}

function requireRecord($db, $table, $id) {
    $query = $db->prepare('SELECT id FROM ' . $table . ' WHERE id = ?');
    $query->execute(array($id));

    if ($query->fetchColumn() === false) {
        throw new InvalidArgumentException('That library record no longer exists.');
    }
}

function addPlaylistVideo($db, $playlistId, $videoId) {
    requireRecord($db, 'playlists', $playlistId);
    requireRecord($db, 'videos', $videoId);
    $query = $db->prepare('SELECT id FROM playlist_videos WHERE playlist_id = ? AND video_id = ?');
    $query->execute(array($playlistId, $videoId));

    if ($query->fetchColumn() !== false) {
        return;
    }

    $query = $db->prepare('INSERT INTO playlist_videos (playlist_id, video_id, position) SELECT ?, ?, COALESCE(MAX(position), -1) + 1 FROM playlist_videos WHERE playlist_id = ?');
    $query->execute(array($playlistId, $videoId, $playlistId));
}

function writeScanProgress($phase, $current = '', $processed = 0, $total = null, $discovered = 0, $force = false) {
    static $last = 0;
    $now = microtime(true);
    if (!$force && $now - $last < 0.25) return;
    $last = $now;
    $file = @fopen(F_PROGRESS, 'c');
    if (!$file) return;
    if (flock($file, LOCK_EX)) {
        $data = json_encode(array('phase' => $phase, 'current' => $current, 'processed' => $processed, 'total' => $total, 'discovered' => $discovered, 'updated' => $now), JSON_INVALID_UTF8_SUBSTITUTE);
        ftruncate($file, 0);
        rewind($file);
        fwrite($file, $data);
        fflush($file);
        flock($file, LOCK_UN);
    }
    fclose($file);
}

function readProgressFile($path) {
    $file = @fopen($path, 'r');
    if (!$file) return null;
    $data = null;
    if (flock($file, LOCK_SH)) {
        $data = json_decode(stream_get_contents($file), true);
        flock($file, LOCK_UN);
    }
    fclose($file);
    return is_array($data) ? $data : null;
}

function thumbnailKey($path, $mtime, $offset) {
    $offset = (int) $offset;
    return hash('sha256', $path . ':' . $mtime . ($offset === 5 ? '' : ':' . $offset));
}

function inspectMedia($path) {
    if (!is_file($path) || !is_readable($path) || !function_exists('proc_open') || !is_file(F_FFPROBE)) {
        logEvent('Media inspection', 'File or FFprobe is unavailable.', array('path' => $path));
        return null;
    }
    $pipes = array();
    $process = proc_open(array(F_FFPROBE, '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height:format=duration', '-of', 'json', $path), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($process)) {
        logEvent('Media inspection', 'Could not start FFprobe.', array('path' => $path));
        return null;
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $output = '';
    $diagnostic = '';
    $deadline = microtime(true) + 5;
    do {
        $output .= stream_get_contents($pipes[1]);
        $diagnostic = substr($diagnostic . stream_get_contents($pipes[2]), -8000);
        $status = proc_get_status($process);
        if (!$status['running']) break;
        usleep(50000);
    } while (microtime(true) < $deadline);
    if ($status['running']) proc_terminate($process);
    $output .= stream_get_contents($pipes[1]);
    $diagnostic = substr($diagnostic . stream_get_contents($pipes[2]), -8000);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    if ($status['running']) {
        logEvent('Media inspection', 'FFprobe exceeded its five-second limit.', array('path' => $path, 'detail' => $diagnostic));
        return null;
    }
    $data = json_decode($output, true);
    if (!is_array($data) || !isset($data['streams'][0])) {
        logEvent('Media inspection', 'FFprobe returned no readable video stream.', array('path' => $path, 'detail' => $diagnostic));
        return null;
    }
    $duration = $data['format']['duration'] ?? null;
    return array('duration' => is_numeric($duration) && (float) $duration >= 0 ? (float) $duration : null, 'video_width' => $data['streams'][0]['width'] ?? null, 'video_height' => $data['streams'][0]['height'] ?? null);
}
