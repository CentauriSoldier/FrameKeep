<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/php/kickstart.php';
    require_once F_FUNCTIONS;
    require_once F_SCANNER;

    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($input)) {
        throw new InvalidArgumentException('A request is required.');
    }

    $action = $input['action'] ?? 'state';
    $scan = null;
    $deletion = null;
    $details = null;

    if ($action === 'playback_claim') {
        $session = (string) ($input['session'] ?? '');
        if ($session === '') throw new InvalidArgumentException('A playback session is required.');
        $query = $db->prepare('UPDATE playback SET session = ?, save_sequence = 0, audio_sequence = 0, autoplay_sequence = 0 WHERE id = 1');
        $query->execute(array($session));
        echo json_encode(array('state' => libraryState($db)), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if (in_array($action, array('playback_save', 'playback_queue', 'playback_autoplay', 'playback_audio'), true)) {
        $db->beginTransaction();
        $sequenceColumn = array('playback_save' => 'save_sequence', 'playback_queue' => 'save_sequence', 'playback_audio' => 'audio_sequence', 'playback_autoplay' => 'autoplay_sequence')[$action];
        $query = $db->prepare('UPDATE playback SET ' . $sequenceColumn . ' = ? WHERE id = 1 AND session = ? AND ' . $sequenceColumn . ' < ?');
        $sequence = (int) ($input['sequence'] ?? 0);
        $query->execute(array($sequence, (string) ($input['session'] ?? ''), $sequence));
        if ($query->rowCount() === 0) {
            $db->rollBack();
            echo json_encode(array('saved' => false, 'stale' => true));
            exit;
        }
        if ($action === 'playback_save') {
            $id = (string) ($input['video'] ?? '');
            requireRecord($db, 'videos', $id);
            $position = (float) ($input['position'] ?? 0);
            if (!is_finite($position) || $position < 0) throw new InvalidArgumentException('Invalid playback position.');
            $query = $db->prepare('UPDATE playback SET video_id = ?, position = ?, queue = ?, queue_index = ? WHERE id = 1');
            $query->execute(array($id, $position, json_encode(array_values($input['queue'] ?? array()), JSON_THROW_ON_ERROR), (int) ($input['queue_index'] ?? 0)));
        } elseif ($action === 'playback_queue') {
            $ids = array_values($input['queue'] ?? array());
            foreach ($ids as $id) requireRecord($db, 'videos', (string) $id);
            $query = $db->prepare('UPDATE playback SET queue = ?, queue_index = ? WHERE id = 1');
            $query->execute(array(json_encode($ids, JSON_THROW_ON_ERROR), (int) ($input['queue_index'] ?? -1)));
        } elseif ($action === 'playback_autoplay') {
            $query = $db->prepare('UPDATE playback SET autoplay = ? WHERE id = 1');
            $query->execute(array(!empty($input['autoplay']) ? 1 : 0));
        }
        if (isset($input['volume']) && $action === 'playback_audio') {
            $volume = (float) $input['volume'];
            if (!is_finite($volume) || $volume < 0 || $volume > 1) throw new InvalidArgumentException('Invalid volume level.');
            $query = $db->prepare('UPDATE playback SET volume = ?, muted = ? WHERE id = 1');
            $query->execute(array($volume, !empty($input['muted']) ? 1 : 0));
        }
        $db->commit();
        echo json_encode(array('saved' => true));
        exit;
    }

    if ($action === 'folder_list') {
        $path = trim((string) ($input['path'] ?? ''));
        $folders = array();
        $parent = null;
        if ($path === '') {
            $roots = array(D_VIDEOS);
            foreach ($db->query('SELECT path FROM libraries')->fetchAll(PDO::FETCH_COLUMN) as $root) $roots[] = $root;
            if (PHP_OS_FAMILY === 'Windows') {
                foreach (range('A', 'Z') as $letter) if (is_dir($letter . ':/')) $roots[] = $letter . ':/';
            } else {
                $roots[] = '/';
            }
            foreach (array_unique($roots) as $root) if (is_dir($root) && is_readable($root)) $folders[] = array('name' => $root, 'path' => $root);
        } else {
            $normalized = str_replace('\\', '/', $path);
            if (!preg_match('/^(?:[A-Za-z]:\/|\/)/', $normalized)) throw new InvalidArgumentException('Use a full folder path.');
            if (!is_dir($path) || !is_readable($path) || ($entries = @scandir($path)) === false) throw new InvalidArgumentException('That folder is unavailable or cannot be read.');
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $child = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $entry;
                if (is_dir($child) && is_readable($child)) $folders[] = array('name' => $entry, 'path' => $child);
            }
            $up = dirname($normalized);
            if ($up !== '.' && $up !== $normalized && is_dir($up)) $parent = $up;
        }
        echo json_encode(array('path' => $path, 'parent' => $parent, 'folders' => $folders), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($action === 'browse') {
        $videoId = (string) ($input['video'] ?? '');
        $query = $db->prepare($videoId !== '' ? 'SELECT path FROM videos WHERE id = ?' : 'SELECT path FROM libraries WHERE id = ?');
        $query->execute(array($videoId !== '' ? $videoId : (int) ($input['library'] ?? 0)));
        $path = $query->fetchColumn();
        $query->closeCursor();
        if ($path === false) throw new InvalidArgumentException('That location is no longer available.');
        $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', array('127.0.0.1', '::1'), true);
        $opened = false;
        if ($local && PHP_OS_FAMILY === 'Windows' && function_exists('proc_open') && ($videoId !== '' ? is_file($path) : is_dir($path))) {
            $argument = ($videoId !== '' ? '/select,' : '') . str_replace('/', '\\', $path);
            $pipes = array();
            $process = @proc_open(array('explorer.exe', $argument), array(0 => array('file', 'NUL', 'r'), 1 => array('file', 'NUL', 'w'), 2 => array('file', 'NUL', 'w')), $pipes, null, null, array('bypass_shell' => true));
            if (is_resource($process)) $opened = proc_close($process) === 0;
        }
        echo json_encode(array('path' => $path, 'opened' => $opened), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($action === 'video_repair') {
        $id = (string) ($input['video'] ?? '');
        $query = $db->prepare('SELECT videos.path FROM videos JOIN libraries ON libraries.id = videos.library_id WHERE videos.id = ? AND libraries.enabled = 1 AND libraries.status = ?');
        $query->execute(array($id, 'online'));
        $path = $query->fetchColumn();
        $query->closeCursor();
        if ($path === false || !is_file($path) || !is_readable($path)) throw new RuntimeException('Repair failed: the file is unavailable or unreadable. The error was recorded in the log.');
        clearstatcache(true, $path);
        $size = filesize($path);
        $mtime = filemtime($path);
        if ($size === false || $mtime === false) throw new RuntimeException('Repair failed: file information could not be read. The error was recorded in the log.');
        if (!is_file(F_FFPROBE) || !function_exists('proc_open')) throw new RuntimeException('Repair failed: configure FFprobe and allow PHP process execution. The error was recorded in the log.');
        $metadata = inspectMedia($path) ?? array('duration' => null, 'video_width' => null, 'video_height' => null);
        clearstatcache(true, $path);
        if (filesize($path) !== $size || filemtime($path) !== $mtime) throw new RuntimeException('Repair stopped: the file changed during inspection. Try again. The error was recorded in the log.');
        $query = $db->prepare('UPDATE videos SET file_size = ?, duration = ?, video_width = ?, video_height = ?, media_checked = 1, media_mtime = ?, thumbnail_key = NULL, thumbnail_offset = NULL WHERE id = ? AND path = ?');
        $query->execute(array($size, $metadata['duration'], $metadata['video_width'], $metadata['video_height'], $mtime, $id, $path));
        if (!$query->rowCount()) throw new RuntimeException('Repair stopped: the library record changed. Try again. The error was recorded in the log.');
        $problems = array();
        if ($size === 0) $problems[] = 'The file is empty.';
        if ($metadata['duration'] === null) $problems[] = 'Duration remains unavailable.';
        if (!$metadata['video_width'] || !$metadata['video_height']) $problems[] = 'Resolution remains unavailable.';
        $success = !$problems;
        $message = $success ? 'File metadata refreshed successfully.' : 'Metadata refreshed, but the file still needs attention: ' . implode(' ', $problems) . ' See the log for inspection details.';
        logEvent('File repair', $message, array('path' => $path, 'success' => $success));
        echo json_encode(array('state' => libraryState($db), 'repair' => array('success' => $success, 'message' => $message)), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($action === 'media_inspect') {
        if (!is_file(F_FFPROBE) || !function_exists('proc_open')) throw new RuntimeException('Configure FFprobe to read video duration and resolution.');
        $records = array();
        foreach (array_slice(array_unique($input['videos'] ?? array()), 0, 3) as $id) {
            $query = $db->prepare('SELECT videos.id, videos.path FROM videos JOIN libraries ON libraries.id = videos.library_id WHERE videos.id = ? AND media_checked = 0 AND missing = 0 AND libraries.enabled = 1');
            $query->execute(array($id));
            $video = $query->fetch();
            $query->closeCursor();
            $query = null;
            if (!$video || !is_file($video['path']) || !is_readable($video['path'])) continue;
            $mtime = filemtime($video['path']);
            $metadata = inspectMedia($video['path']) ?? array('duration' => null, 'video_width' => null, 'video_height' => null);
            $query = $db->prepare('UPDATE videos SET duration = ?, video_width = ?, video_height = ?, media_checked = 1, media_mtime = ? WHERE id = ? AND path = ?');
            $query->execute(array($metadata['duration'], $metadata['video_width'], $metadata['video_height'], $mtime, $video['id'], $video['path']));
            if ($query->rowCount()) $records[] = array_merge(array('id' => $video['id'], 'media_checked' => 1), $metadata);
            $query = null;
        }
        echo json_encode(array('media' => $records), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($action === 'scan') {
        $scan = scanVideos($db);
    } elseif ($action === 'video_details') {
        $details = videoDetails($db, (string) ($input['video'] ?? ''));
    } elseif ($action === 'video_delete') {
        if (($input['confirmed'] ?? false) !== true) throw new InvalidArgumentException('Confirm permanent file deletion first.');
        $lock = fopen(F_SCAN_LOCK, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('A scan is running. Try deleting again when it finishes.');
        $deletion = array('deleted' => 0, 'errors' => array());
        try {
            foreach (array_unique($input['videos'] ?? array()) as $videoId) {
                $removed = false;
                $path = (string) $videoId;
                try {
                    $db->beginTransaction();
                    $db->exec('UPDATE playback SET save_sequence = save_sequence WHERE id = 1');
                    $query = $db->prepare('SELECT path FROM videos WHERE id = ?');
                    $query->execute(array((string) $videoId));
                    $path = $query->fetchColumn();
                    $query->closeCursor();
                    if ($path === false) {
                        $db->rollBack();
                        continue;
                    }
                    $query = $db->prepare('DELETE FROM videos WHERE id = ?');
                    $query->execute(array((string) $videoId));
                    $query = $db->prepare('DELETE FROM file_inventory WHERE path = ?');
                    $query->execute(array($path));
                    if (!is_file($path) || !@unlink($path)) throw new RuntimeException('File could not be removed.');
                    $removed = true;
                    $db->commit();
                    $deletion['deleted']++;
                } catch (Throwable $error) {
                    if ($db->inTransaction()) $db->rollBack();
                    $deletion['errors'][] = ($removed ? 'File removed, but database cleanup failed; rescan required: ' : 'Not deleted: ') . $path . ' — ' . $error->getMessage();
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    } elseif ($action === 'library_delete') {
        if (($input['confirmed'] ?? false) !== true) throw new InvalidArgumentException('Confirm removal of library records first.');
        $libraryId = (int) ($input['library'] ?? 0);
        $lock = fopen(F_SCAN_LOCK, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('A scan is running. Try removing the library when it finishes.');
        try {
            $db->beginTransaction();
            requireRecord($db, 'libraries', $libraryId);
            $query = $db->prepare('DELETE FROM videos WHERE library_id = ?');
            $query->execute(array($libraryId));
            $query = $db->prepare('DELETE FROM file_inventory WHERE library_id = ?');
            $query->execute(array($libraryId));
            $query = $db->prepare('DELETE FROM libraries WHERE id = ?');
            $query->execute(array($libraryId));
            $db->commit();
        } finally {
            if ($db->inTransaction()) $db->rollBack();
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    } elseif ($action === 'thumbnail_clear') {
        $lock = fopen(F_THUMBNAIL_LOCK, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Thumbnails are being generated. Try clearing again shortly.');
        try {
            foreach (is_dir(D_THUMBNAILS) ? scandir(D_THUMBNAILS) : array() as $entry) {
                if (!preg_match('/^[a-f0-9]{64}\.(?:jpg|tmp\.jpg|jpg\.lock)$/', $entry)) continue;
                $path = D_THUMBNAILS . '/' . $entry;
                if (!is_link($path) && is_file($path) && !unlink($path)) throw new RuntimeException('A cached thumbnail could not be removed.');
            }
            $db->exec('UPDATE app_settings SET thumbnail_version = thumbnail_version + 1 WHERE id = 1');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    } elseif ($action !== 'state') {
        $db->beginTransaction();

        switch ($action) {
            case 'video_tags_bulk':
                $videos = array_unique($input['videos'] ?? array());
                $tags = array_unique(array_map('intval', $input['tags'] ?? array()));
                $mode = (string) ($input['mode'] ?? '');
                if (!$videos || !$tags || !in_array($mode, array('add', 'remove'), true)) throw new InvalidArgumentException('Select videos, tags, and an add/remove action.');
                foreach ($videos as $id) requireRecord($db, 'videos', (string) $id);
                foreach ($tags as $id) requireRecord($db, 'tags', $id);
                $query = $db->prepare($mode === 'add' ? 'INSERT OR IGNORE INTO video_tags(video_id, tag_id) VALUES (?, ?)' : 'DELETE FROM video_tags WHERE video_id = ? AND tag_id = ?');
                foreach ($videos as $videoId) foreach ($tags as $tagId) $query->execute(array((string) $videoId, $tagId));
                break;

            case 'library_add':
                $name = requiredName($input['name'] ?? '');
                $path = trim((string) ($input['path'] ?? ''));
                $normalized = str_replace('\\', '/', $path);
                if (!preg_match('/^(?:[A-Za-z]:\/|\/)/', $normalized)) throw new InvalidArgumentException('Use a full local or network folder path.');
                $normalized = rtrim($normalized, '/') . '/';
                $compare = PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
                foreach ($db->query('SELECT path FROM libraries')->fetchAll(PDO::FETCH_COLUMN) as $existing) {
                    $existing = rtrim(str_replace('\\', '/', $existing), '/') . '/';
                    if (PHP_OS_FAMILY === 'Windows') $existing = strtolower($existing);
                    if (str_starts_with($compare, $existing) || str_starts_with($existing, $compare)) throw new InvalidArgumentException('That location overlaps an existing library. Choose a separate folder.');
                }
                $query = $db->prepare('INSERT INTO libraries (name, path) VALUES (?, ?)');
                $query->execute(array($name, $path));
                break;

            case 'library_enabled':
                requireRecord($db, 'libraries', (int) ($input['library'] ?? 0));
                $query = $db->prepare('UPDATE libraries SET enabled = ? WHERE id = ?');
                $query->execute(array(!empty($input['enabled']) ? 1 : 0, (int) $input['library']));
                break;

            case 'missing_remove':
                if (($input['confirmed'] ?? false) !== true) throw new InvalidArgumentException('Confirm removal of this missing library record.');
                $query = $db->prepare('SELECT path, library_id, missing FROM videos WHERE id = ?');
                $query->execute(array((string) ($input['video'] ?? '')));
                $video = $query->fetch();
                $query->closeCursor();
                if (!$video || !$video['missing']) throw new InvalidArgumentException('That record is no longer marked missing.');
                $locations = array_column(libraryLocations($db), null, 'id');
                if (($locations[$video['library_id']]['status'] ?? 'offline') !== 'online') throw new RuntimeException('The library is unavailable. Its records are preserved.');
                clearstatcache(true, $video['path']);
                if (file_exists($video['path'])) throw new RuntimeException('The file is present again. Rescan to update its record.');
                $query = $db->prepare('DELETE FROM videos WHERE id = ? AND missing = 1');
                $query->execute(array((string) $input['video']));
                break;

            case 'video_rating':
                $rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_INT);
                if ($rating === false || $rating < 0 || $rating > 5) throw new InvalidArgumentException('Choose a rating from zero to five.');
                requireRecord($db, 'videos', (string) ($input['video'] ?? ''));
                $query = $db->prepare('UPDATE videos SET rating = ? WHERE id = ?');
                $query->execute(array($rating, (string) $input['video']));
                break;

            case 'pagination_save':
                $pageSize = filter_var($input['page_size'] ?? 50, FILTER_VALIDATE_INT);
                if (!in_array($pageSize, array(10, 25, 50, 100, 250, 500), true)) throw new InvalidArgumentException('Choose a supported page size.');
                $query = $db->prepare('UPDATE app_settings SET page_size = ?, fill_last_row = ? WHERE id = 1');
                $query->execute(array($pageSize, !empty($input['fill_last_row']) ? 1 : 0));
                break;

            case 'settings_save':
                $frequency = filter_var($input['scan_frequency'] ?? SCAN_FREQUENCY, FILTER_VALIDATE_INT);
                $size = (string) ($input['thumbnail_size'] ?? 'small');
                $thumbnailTime = filter_var($input['thumbnail_time'] ?? 10, FILTER_VALIDATE_INT);
                if ($thumbnailTime === false || $thumbnailTime < 0 || $thumbnailTime > 86400) throw new InvalidArgumentException('Thumbnail time must be between 0 and 86400 seconds.');
                if ($frequency === false || $frequency < 1 || $frequency > 86400) throw new InvalidArgumentException('Scan interval must be between 1 and 86400 seconds.');
                if (!in_array($size, array('small', 'medium', 'large'), true)) throw new InvalidArgumentException('Choose a thumbnail size.');
                $query = $db->prepare('UPDATE app_settings SET scan_on_start = ?, auto_orphan = ?, auto_scan = ?, scan_frequency = ?, thumbnail_size = ?, thumbnail_version = thumbnail_version + (thumbnail_time != ?), thumbnail_time = ?, scroll_to_player = ? WHERE id = 1');
                $query->execute(array(!empty($input['scan_on_start']) ? 1 : 0, !empty($input['auto_orphan']) ? 1 : 0, !empty($input['auto_scan']) ? 1 : 0, $frequency, $size, $thumbnailTime, $thumbnailTime, !empty($input['scroll_to_player']) ? 1 : 0));
                $query = $db->prepare('UPDATE playback SET autoplay = ? WHERE id = 1');
                $query->execute(array(!empty($input['autoplay']) ? 1 : 0));
                break;

            case 'video_relink':
                $videoId = (string) ($input['video'] ?? '');
                $path = (string) ($input['path'] ?? '');
                $query = $db->prepare('SELECT filename, file_size FROM videos WHERE id = ? AND missing = 1');
                $query->execute(array($videoId));
                $old = $query->fetch();
                $query->closeCursor();
                $query = $db->prepare('SELECT filename, file_size, library_id FROM file_inventory WHERE path = ? AND path NOT IN (SELECT path FROM videos)');
                $query->execute(array($path));
                $candidate = $query->fetch();
                $query->closeCursor();
                $size = @filesize($path);
                $availableLibraries = array_column(libraryLocations($db), 'status', 'id');
                if ($candidate && ($availableLibraries[$candidate['library_id']] ?? 'offline') !== 'online') throw new InvalidArgumentException('That library is unavailable.');
                $sameName = $old && $candidate && $candidate['filename'] === $old['filename'];
                $sameSize = $old && $old['file_size'] !== null && $size !== false && (int) $old['file_size'] === $size;
                if (!$old || !$candidate || !is_file($path) || (!$sameName && !$sameSize)) throw new InvalidArgumentException('That match is no longer available. Rescan and try again.');
                $query = $db->prepare('UPDATE videos SET path = ?, filename = ?, missing = 0, file_size = ?, library_id = ? WHERE id = ?');
                $query->execute(array($path, basename(str_replace('\\', '/', $path)), $size === false ? null : $size, $candidate['library_id'], $videoId));
                break;
            case 'video_tags_save':
            case 'video_playlists_add':
                $videoId = (string) ($input['video'] ?? '');
                requireRecord($db, 'videos', $videoId);
                $choices = array_unique(array_map('intval', $input['choices'] ?? array()));
                foreach ($choices as $choice) requireRecord($db, $action === 'video_tags_save' ? 'tags' : 'playlists', $choice);
                if ($action === 'video_tags_save') {
                    $query = $db->prepare('DELETE FROM video_tags WHERE video_id = ?');
                    $query->execute(array($videoId));
                    $query = $db->prepare('INSERT INTO video_tags (video_id, tag_id) VALUES (?, ?)');
                    foreach ($choices as $choice) $query->execute(array($videoId, $choice));
                } else {
                    foreach ($choices as $choice) addPlaylistVideo($db, $choice, $videoId);
                }
                break;

            case 'video_tag':
                $videoId = (string) ($input['video'] ?? '');
                $tagId = (int) ($input['tag'] ?? 0);
                requireRecord($db, 'videos', $videoId);
                requireRecord($db, 'tags', $tagId);
                $sql = !empty($input['assigned'])
                    ? 'INSERT OR IGNORE INTO video_tags (video_id, tag_id) VALUES (?, ?)'
                    : 'DELETE FROM video_tags WHERE video_id = ? AND tag_id = ?';
                $query = $db->prepare($sql);
                $query->execute(array($videoId, $tagId));
                break;

            case 'video_save':
                $id = (string) ($input['id'] ?? '');
                requireRecord($db, 'videos', $id);
                $query = $db->prepare('UPDATE videos SET name = ? WHERE id = ?');
                $query->execute(array(requiredName($input['name'] ?? ''), $id));
                $query = $db->prepare('DELETE FROM video_tags WHERE video_id = ?');
                $query->execute(array($id));

                foreach (array_unique($input['tags'] ?? array()) as $tagId) {
                    requireRecord($db, 'tags', (int) $tagId);
                    $query = $db->prepare('INSERT INTO video_tags (video_id, tag_id) VALUES (?, ?)');
                    $query->execute(array($id, (int) $tagId));
                }

                $wantedPlaylists = array_map('intval', $input['playlists'] ?? array());
                $query = $db->prepare('SELECT DISTINCT playlist_id FROM playlist_videos WHERE video_id = ?');
                $query->execute(array($id));

                foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $playlistId) {
                    if (!in_array((int) $playlistId, $wantedPlaylists, true)) {
                        $delete = $db->prepare('DELETE FROM playlist_videos WHERE video_id = ? AND playlist_id = ?');
                        $delete->execute(array($id, $playlistId));
                    }
                }

                foreach (array_unique($wantedPlaylists) as $playlistId) {
                    addPlaylistVideo($db, $playlistId, $id);
                }

                break;

            case 'tag_save':
                if (($input['id'] ?? null) === 'untagged') {
                    $textColor = $input['text_color'] ?? '#ffffff';
                    $backgroundColor = $input['background_color'] ?? '#6c757d';
                    $font = $input['font'] ?? 'system-ui';
                    $fontSize = (int) ($input['font_size'] ?? 12);
                    if (!preg_match('/^#[a-fA-F0-9]{6}$/', $textColor) || !preg_match('/^#[a-fA-F0-9]{6}$/', $backgroundColor) || !in_array($font, array('system-ui', 'Arial', 'Verdana', 'Georgia', 'serif', 'monospace'), true) || $fontSize < 10 || $fontSize > 24) throw new InvalidArgumentException('Choose valid tag colors, a listed font, and a size from 10 to 24.');
                    $query = $db->prepare('UPDATE app_settings SET untagged_style = ? WHERE id = 1');
                    $query->execute(array(json_encode(array('text_color' => $textColor, 'background_color' => $backgroundColor, 'font' => $font, 'font_size' => $fontSize), JSON_THROW_ON_ERROR)));
                    break;
                }
                $name = requiredName($input['name'] ?? '');
                $id = (int) ($input['id'] ?? 0);
                $query = $db->prepare('SELECT id FROM tags WHERE name = ? COLLATE NOCASE AND id != ?');
                $query->execute(array($name, $id));

                if ($query->fetchColumn() !== false) {
                    throw new InvalidArgumentException('A tag with that name already exists.');
                }

                $query = $db->prepare('SELECT text_color, background_color, font, font_size FROM tags WHERE id = ?');
                $query->execute(array($id));
                $previous = $query->fetch() ?: array();
                $textColor = $input['text_color'] ?? $previous['text_color'] ?? '#ffffff';
                $backgroundColor = $input['background_color'] ?? $previous['background_color'] ?? '#6c757d';
                $font = $input['font'] ?? $previous['font'] ?? 'system-ui';
                $fontSize = (int) ($input['font_size'] ?? $previous['font_size'] ?? 12);
                if (!preg_match('/^#[a-fA-F0-9]{6}$/', $textColor) || !preg_match('/^#[a-fA-F0-9]{6}$/', $backgroundColor) || !in_array($font, array('system-ui', 'Arial', 'Verdana', 'Georgia', 'serif', 'monospace'), true) || $fontSize < 10 || $fontSize > 24) throw new InvalidArgumentException('Choose valid tag colors, a listed font, and a size from 10 to 24.');

                if ($id) {
                    requireRecord($db, 'tags', $id);
                    $query = $db->prepare('UPDATE tags SET name = ?, text_color = ?, background_color = ?, font = ?, font_size = ? WHERE id = ?');
                    $query->execute(array($name, $textColor, $backgroundColor, $font, $fontSize, $id));
                } else {
                    $query = $db->prepare('INSERT INTO tags (name, text_color, background_color, font, font_size) VALUES (?, ?, ?, ?, ?)');
                    $query->execute(array($name, $textColor, $backgroundColor, $font, $fontSize));
                }

                break;

            case 'playlist_save':
                $name = requiredName($input['name'] ?? '');
                $id = (int) ($input['id'] ?? 0);
                $textColor = $input['text_color'] ?? '#ffffff';
                $backgroundColor = $input['background_color'] ?? '#6c757d';
                $font = $input['font'] ?? 'system-ui';
                $fontSize = (int) ($input['font_size'] ?? 12);
                if (!preg_match('/^#[a-fA-F0-9]{6}$/', $textColor) || !preg_match('/^#[a-fA-F0-9]{6}$/', $backgroundColor) || !in_array($font, array('system-ui', 'Arial', 'Verdana', 'Georgia', 'serif', 'monospace'), true) || $fontSize < 10 || $fontSize > 24) throw new InvalidArgumentException('Choose valid playlist colors, a listed font, and a size from 10 to 24.');
                $cover = (string) ($input['cover_video'] ?? '');
                if ($cover !== '') {
                    $query = $db->prepare('SELECT 1 FROM playlist_videos WHERE playlist_id = ? AND video_id = ?');
                    $query->execute(array($id, $cover));
                    if (!$query->fetchColumn()) throw new InvalidArgumentException('Choose a cover video from this playlist.');
                }

                if ($id) {
                    requireRecord($db, 'playlists', $id);
                    $query = $db->prepare('UPDATE playlists SET name = ?, text_color = ?, background_color = ?, font = ?, font_size = ?, cover_video = ? WHERE id = ?');
                    $query->execute(array($name, $textColor, $backgroundColor, $font, $fontSize, $cover ?: null, $id));
                } else {
                    $query = $db->prepare('INSERT INTO playlists (name, text_color, background_color, font, font_size) VALUES (?, ?, ?, ?, ?)');
                    $query->execute(array($name, $textColor, $backgroundColor, $font, $fontSize));
                }

                break;

            case 'tag_delete':
            case 'playlist_delete':
                $table = $action === 'tag_delete' ? 'tags' : 'playlists';
                $query = $db->prepare('DELETE FROM ' . $table . ' WHERE id = ?');
                $query->execute(array((int) ($input['id'] ?? 0)));
                break;

            case 'playlist_batch':
                $target = (int) ($input['target'] ?? 0);
                $source = (int) ($input['source'] ?? 0);

                foreach (array_unique($input['videos'] ?? array()) as $videoId) {
                    addPlaylistVideo($db, $target, (string) $videoId);

                    if (($input['mode'] ?? 'add') === 'move' && $source && $source !== $target) {
                        $query = $db->prepare('DELETE FROM playlist_videos WHERE playlist_id = ? AND video_id = ?');
                        $query->execute(array($source, $videoId));
                    }
                }

                break;

            case 'playlist_remove':
                $query = $db->prepare('DELETE FROM playlist_videos WHERE playlist_id = ? AND video_id = ?');
                $query->execute(array((int) ($input['playlist'] ?? 0), (string) ($input['video'] ?? '')));
                break;

            case 'playlist_order':
                $playlistId = (int) ($input['playlist'] ?? 0);
                requireRecord($db, 'playlists', $playlistId);
                $query = $db->prepare('SELECT id FROM playlist_videos WHERE playlist_id = ?');
                $query->execute(array($playlistId));
                $existing = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
                $order = array_map('intval', $input['items'] ?? array());
                $sorted = $order;
                sort($sorted);
                sort($existing);

                if ($existing !== $sorted || count(array_unique($order)) !== count($order)) {
                    throw new InvalidArgumentException('Playlist changed. Please try reordering again.');
                }

                $query = $db->prepare('UPDATE playlist_videos SET position = ? WHERE id = ? AND playlist_id = ?');

                foreach ($order as $position => $itemId) {
                    $query->execute(array($position, $itemId, $playlistId));
                }

                break;

            default:
                throw new InvalidArgumentException('Unknown library action.');
        }

        $db->commit();
    }

    echo json_encode(array('state' => libraryState($db), 'scan' => $scan, 'deletion' => $deletion, 'details' => $details), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    logEvent('API', $error->getMessage(), array('action' => $action ?? 'startup'));
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code($error instanceof InvalidArgumentException || $error instanceof JsonException ? 400 : 500);
    echo json_encode(array('error' => $error->getMessage()), JSON_INVALID_UTF8_SUBSTITUTE);
}
