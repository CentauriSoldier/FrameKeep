<?php

function cleanVideoName($filename) {
    $name = preg_replace('/[^A-Za-z0-9\s]+/', ' ', pathinfo($filename, PATHINFO_FILENAME));
    $name = trim(preg_replace('/\s+/', ' ', $name));

    if ($name === '') {
        return 'Video ' . strtoupper(bin2hex(random_bytes(4)));
    }

    return function_exists('mb_convert_case') ? mb_convert_case($name, MB_CASE_TITLE, 'UTF-8') : ucwords(strtolower($name));
}

function scanVideos($db) {
    set_time_limit(0);
    $result = array('status' => 'complete', 'added' => 0, 'relocated' => 0, 'orphaned' => 0, 'errors' => array());
    $lock = fopen(F_SCAN_LOCK, 'c');

    if ($lock === false) {
        throw new RuntimeException('Unable to open the library scan lock.');
    }

    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        $result['status'] = 'busy';

        return $result;
    }

    try {
        writeScanProgress('discovering', '', 0, null, 0, true);
        $thumbnailTime = (int) $db->query('SELECT thumbnail_time FROM app_settings WHERE id = 1')->fetchColumn();
        $libraries = $db->query('SELECT id, name, path, enabled FROM libraries ORDER BY id')->fetchAll();
        $videos = array();
        $sizes = array();
        $thumbnailKeys = array();
        $mediaTimes = array();
        $owners = array();
        $statuses = array();
        $result['offline'] = array();
        foreach ($libraries as $library) {
            $libraryId = (int) $library['id'];
            $root = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $library['path']);
            if (strlen($root) > 1) $root = rtrim($root, '/\\');
            if (!$library['enabled']) {
                $statuses[$libraryId] = 'disabled';
                continue;
            }
            clearstatcache(true, $root);
            if (!is_dir($root) || !is_readable($root) || @scandir($root) === false) {
                $statuses[$libraryId] = 'offline';
                $result['offline'][] = $library['name'];
                continue;
            }
            writeScanProgress('discovering', $library['name'], 0, null, count($videos), true);
            $statuses[$libraryId] = 'online';
            $folders = array($root);
            while ($folders) {
                $folder = array_pop($folders);
                $entries = @scandir($folder);

                if ($entries === false) {
                    $result['errors'][] = $folder;
                    $statuses[$libraryId] = 'incomplete';

                    continue;
                }

                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }

                    $path = $folder . DIRECTORY_SEPARATOR . $entry;
                    writeScanProgress('discovering', $entry, 0, null, count($videos));

                    if (is_link($path)) {
                        continue;
                    }

                    if (is_dir($path)) {
                        if (!in_array($entry, array('.TPDLNA', '@eaDir', '@tmp', '$RECYCLE.BIN', 'System Volume Information'), true)) {
                            $folders[] = $path;
                        }

                        continue;
                    }

                    if (!is_file($path) || !in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), VIDEO_EXTENSIONS, true)) {
                        continue;
                    }

                    $owners[$path] = $libraryId;
                    $videos[$path] = cleanVideoName($entry);
                    $size = @filesize($path);
                    $sizes[$path] = $size === false ? null : $size;
                    $mtime = @filemtime($path);
                    $mediaTimes[$path] = $mtime === false ? null : $mtime;
                    $thumbnailKeys[$path] = $mtime === false ? null : thumbnailKey($path, $mtime, $thumbnailTime);
                    if ($size === false) {
                        $statuses[$libraryId] = 'incomplete';
                        $result['errors'][] = $path;
                    }
                }
            }

        }
        $complete = count(array_filter($statuses, function ($status) { return $status !== 'online'; })) === 0;
        $db->beginTransaction();

        try {
            $statusUpdate = $db->prepare('UPDATE libraries SET status = ? WHERE id = ?');
            foreach ($statuses as $id => $status) $statusUpdate->execute(array($status, $id));
            $autoOrphan = (bool) $db->query('SELECT auto_orphan FROM app_settings WHERE id = 1')->fetchColumn();
            $scannedNames = array();
            $scannedSizes = array();
            foreach ($videos as $path => $name) {
                $scannedNames[strtolower(basename(str_replace('\\', '/', $path)))] = true;
                if ($sizes[$path] !== null) $scannedSizes[(string) $sizes[$path]] = true;
            }
            $orphan = $db->prepare('DELETE FROM videos WHERE id = ?');
            $known = $db->query('SELECT id, path, filename, file_size, library_id FROM videos')->fetchAll();
            writeScanProgress('matching', '', 0, count($known), count($videos), true);
            $processed = 0;
            $mediaReset = $db->prepare('UPDATE videos SET media_checked = 0, duration = NULL, video_width = NULL, video_height = NULL, media_mtime = ? WHERE path = ? AND media_mtime IS NOT ?');
            $keyUpdate = $db->prepare('UPDATE videos SET thumbnail_key = ?, thumbnail_offset = ? WHERE path = ?');
            $knownPaths = array_column($known, 'id', 'path');
            $missingNames = array();
            $missingSizes = array();
            $missingIdentities = array();
            foreach ($known as $video) {
                if (!isset($videos[$video['path']])) {
                    $key = $video['filename'] . ':' . $video['file_size'];
                    $missingIdentities[$key] = ($missingIdentities[$key] ?? 0) + 1;
                }
            }
            $mark = $db->prepare('UPDATE videos SET missing = ? WHERE id = ?');
            $sizeUpdate = $db->prepare('UPDATE videos SET file_size = ? WHERE id = ?');
            $relink = $db->prepare('UPDATE videos SET path = ?, library_id = ?, missing = 0 WHERE id = ?');
            foreach ($known as $video) {
                writeScanProgress('matching', $video['filename'], ++$processed, count($known), count($videos));
                $missing = !isset($videos[$video['path']]);
                if (!$missing && $sizes[$video['path']] !== null) $sizeUpdate->execute(array($sizes[$video['path']], $video['id']));
                if ($missing && $complete && $video['file_size'] !== null && ($missingIdentities[$video['filename'] . ':' . $video['file_size']] ?? 0) === 1) {
                    $matches = array();
                    foreach ($videos as $path => $name) {
                        if (!isset($knownPaths[$path]) && basename(str_replace('\\', '/', $path)) === $video['filename'] && $sizes[$path] !== null && (int) $sizes[$path] === (int) $video['file_size']) $matches[] = $path;
                    }
                    if (count($matches) === 1) {
                        $relink->execute(array($matches[0], $owners[$matches[0]], $video['id']));
                        $knownPaths[$matches[0]] = $video['id'];
                        $missing = false;
                        $result['relocated']++;
                    }
                }
                if ($autoOrphan && $missing && $complete && $video['file_size'] !== null && !isset($scannedNames[strtolower($video['filename'])]) && !isset($scannedSizes[(string) $video['file_size']])) {
                    $orphan->execute(array($video['id']));
                    $result['orphaned'] += $orphan->rowCount();
                    continue;
                }
                if (($statuses[$video['library_id']] ?? 'offline') === 'online') $mark->execute(array((int) $missing, $video['id']));
                if ($missing) {
                    $missingNames[$video['filename']] = true;
                    if ($video['file_size'] !== null) $missingSizes[(string) $video['file_size']] = true;
                }
            }
            $purge = $db->prepare('DELETE FROM file_inventory WHERE library_id = ?');
            foreach ($statuses as $id => $status) if ($status === 'online') $purge->execute(array($id));
            $inventory = $db->prepare('INSERT OR REPLACE INTO file_inventory (path, filename, file_size, library_id) VALUES (?, ?, ?, ?)');
            $insert = $db->prepare('INSERT INTO videos (id, name, path, filename, file_size, library_id, thumbnail_key, thumbnail_offset) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(path) DO NOTHING');

            writeScanProgress('importing', '', 0, count($videos), count($videos), true);
            $processed = 0;
            foreach ($videos as $path => $name) {
                writeScanProgress('importing', basename(str_replace('\\', '/', $path)), ++$processed, count($videos), count($videos));
                $mediaReset->execute(array($mediaTimes[$path], $path, $mediaTimes[$path]));
                $keyUpdate->execute(array($thumbnailKeys[$path], $thumbnailTime, $path));
                $filename = basename(str_replace('\\', '/', $path));
                $inventory->execute(array($path, $filename, $sizes[$path], $owners[$path]));
                if (isset($knownPaths[$path]) || isset($missingNames[$filename]) || ($sizes[$path] !== null && isset($missingSizes[(string) $sizes[$path]]))) continue;
                $insert->execute(array(bin2hex(random_bytes(16)), $name, $path, $filename, $sizes[$path], $owners[$path], $thumbnailKeys[$path], $thumbnailTime));
                $result['added'] += $insert->rowCount();
            }

            $db->commit();
            writeScanProgress('complete', '', count($videos), count($videos), count($videos), true);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $error;
        }

        return $result;
    } catch (Throwable $error) {
        writeScanProgress('failed', '', 0, null, 0, true);
        throw $error;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
