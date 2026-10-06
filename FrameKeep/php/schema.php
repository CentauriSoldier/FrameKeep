<?php

$db = new PDO('sqlite:' . F_DATA);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec('PRAGMA busy_timeout = 5000');

$schemaVersion = 4;
if ((int) $db->query('PRAGMA user_version')->fetchColumn() >= $schemaVersion) return;

$db->exec('PRAGMA busy_timeout = 30000');
$schemaTransaction = false;

try {
    $db->exec('BEGIN IMMEDIATE');
    $schemaTransaction = true;
    if ((int) $db->query('PRAGMA user_version')->fetchColumn() >= $schemaVersion) {
        $db->exec('COMMIT');
        $schemaTransaction = false;
        $db->exec('PRAGMA busy_timeout = 5000');
        return;
    }
    $db->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS videos (
            id      TEXT PRIMARY KEY NOT NULL,
            name    TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS tags (
            id      INTEGER PRIMARY KEY,
            name    TEXT NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS video_tags (
            video_id    TEXT NOT NULL,
            tag_id      INTEGER NOT NULL,
            PRIMARY KEY (video_id, tag_id),
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS playlists (
            id      INTEGER PRIMARY KEY,
            name    TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS playlist_videos (
            id              INTEGER PRIMARY KEY,
            playlist_id     INTEGER NOT NULL,
            video_id        TEXT NOT NULL,
            position        INTEGER NOT NULL CHECK (position >= 0),
            FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS video_tags_tag_id
            ON video_tags(tag_id);

        CREATE INDEX IF NOT EXISTS playlist_videos_order
            ON playlist_videos(playlist_id, position, id);

        CREATE INDEX IF NOT EXISTS playlist_videos_video_id
            ON playlist_videos(video_id);
        SQL);

    $columns = array_column($db->query('PRAGMA table_info(videos)')->fetchAll(), 'name');
    if (!in_array('path', $columns, true)) {
        $db->exec('ALTER TABLE videos ADD COLUMN path TEXT');
        $db->exec('ALTER TABLE videos ADD COLUMN filename TEXT');
        $db->exec('ALTER TABLE videos ADD COLUMN missing INTEGER NOT NULL DEFAULT 0');
        $update = $db->prepare('UPDATE videos SET path = ?, filename = ? WHERE id = ?');
        foreach ($db->query('SELECT id FROM videos')->fetchAll() as $video) {
            $update->execute(array($video['id'], basename(str_replace('\\', '/', $video['id'])), $video['id']));
        }
    }
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS videos_path ON videos(path)');
    $db->exec('CREATE TABLE IF NOT EXISTS file_inventory (path TEXT PRIMARY KEY, filename TEXT NOT NULL)');
    $db->exec("CREATE TABLE IF NOT EXISTS playback (id INTEGER PRIMARY KEY CHECK(id = 1), video_id TEXT, position REAL NOT NULL DEFAULT 0, autoplay INTEGER NOT NULL DEFAULT 0, queue TEXT NOT NULL DEFAULT '[]', queue_index INTEGER NOT NULL DEFAULT 0, FOREIGN KEY(video_id) REFERENCES videos(id) ON DELETE SET NULL)");
    $db->exec('INSERT OR IGNORE INTO playback (id) VALUES (1)');
    $playbackColumns = array_column($db->query('PRAGMA table_info(playback)')->fetchAll(), 'name');
    if (!in_array('volume', $playbackColumns, true)) {
        $db->exec('ALTER TABLE playback ADD COLUMN volume REAL NOT NULL DEFAULT 1');
        $db->exec('ALTER TABLE playback ADD COLUMN muted INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('session', $playbackColumns, true)) {
        $db->exec("ALTER TABLE playback ADD COLUMN session TEXT NOT NULL DEFAULT ''");
        $db->exec('ALTER TABLE playback ADD COLUMN save_sequence INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('audio_sequence', $playbackColumns, true)) {
        $db->exec('ALTER TABLE playback ADD COLUMN audio_sequence INTEGER NOT NULL DEFAULT 0');
        $db->exec('ALTER TABLE playback ADD COLUMN autoplay_sequence INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('file_size', $columns, true)) $db->exec('ALTER TABLE videos ADD COLUMN file_size INTEGER');
    $inventoryColumns = array_column($db->query('PRAGMA table_info(file_inventory)')->fetchAll(), 'name');
    if (!in_array('file_size', $inventoryColumns, true)) $db->exec('ALTER TABLE file_inventory ADD COLUMN file_size INTEGER');
    $tagColumns = array_column($db->query('PRAGMA table_info(tags)')->fetchAll(), 'name');
    if (!in_array('text_color', $tagColumns, true)) {
        $db->exec("ALTER TABLE tags ADD COLUMN text_color TEXT NOT NULL DEFAULT '#ffffff'");
        $db->exec("ALTER TABLE tags ADD COLUMN background_color TEXT NOT NULL DEFAULT '#6c757d'");
        $db->exec("ALTER TABLE tags ADD COLUMN font TEXT NOT NULL DEFAULT 'system-ui'");
        $db->exec('ALTER TABLE tags ADD COLUMN font_size INTEGER NOT NULL DEFAULT 12');
    }

    $db->exec('CREATE TABLE IF NOT EXISTS app_settings (id INTEGER PRIMARY KEY CHECK(id = 1), scan_on_start INTEGER NOT NULL DEFAULT 1)');
    $db->exec('INSERT OR IGNORE INTO app_settings (id) VALUES (1)');
    $settingsColumns = array_column($db->query('PRAGMA table_info(app_settings)')->fetchAll(), 'name');
    if (!in_array('auto_scan', $settingsColumns, true)) {
        $db->exec('ALTER TABLE app_settings ADD COLUMN auto_scan INTEGER NOT NULL DEFAULT 1');
        $db->exec('ALTER TABLE app_settings ADD COLUMN scan_frequency INTEGER NOT NULL DEFAULT ' . (int) SCAN_FREQUENCY);
        $db->exec("ALTER TABLE app_settings ADD COLUMN thumbnail_size TEXT NOT NULL DEFAULT 'small'");
        $db->exec('ALTER TABLE app_settings ADD COLUMN thumbnail_version INTEGER NOT NULL DEFAULT 0');
    }

    if (!in_array('auto_orphan', $settingsColumns, true)) $db->exec('ALTER TABLE app_settings ADD COLUMN auto_orphan INTEGER NOT NULL DEFAULT 0');

    $db->exec("CREATE TABLE IF NOT EXISTS libraries (id INTEGER PRIMARY KEY, name TEXT NOT NULL, path TEXT NOT NULL UNIQUE, enabled INTEGER NOT NULL DEFAULT 1, status TEXT NOT NULL DEFAULT 'online')");
    if (!in_array('library_id', $columns, true)) {
        $query = $db->prepare('INSERT INTO libraries (name, path) VALUES (?, ?)');
        $query->execute(array('Primary library', D_VIDEOS));
        $libraryId = (int) $db->lastInsertId();
        $db->exec('ALTER TABLE videos ADD COLUMN library_id INTEGER REFERENCES libraries(id)');
        $db->exec('ALTER TABLE file_inventory ADD COLUMN library_id INTEGER REFERENCES libraries(id)');
        $query = $db->prepare('UPDATE videos SET library_id = ?');
        $query->execute(array($libraryId));
        $query = $db->prepare('UPDATE file_inventory SET library_id = ?');
        $query->execute(array($libraryId));
    }

    if (!in_array('rating', $columns, true)) $db->exec('ALTER TABLE videos ADD COLUMN rating INTEGER NOT NULL DEFAULT 0 CHECK (rating BETWEEN 0 AND 5)');
    if (!in_array('duration', $columns, true)) $db->exec('ALTER TABLE videos ADD COLUMN duration REAL');
    if (!in_array('video_width', $columns, true)) $db->exec('ALTER TABLE videos ADD COLUMN video_width INTEGER');
    if (!in_array('video_height', $columns, true)) $db->exec('ALTER TABLE videos ADD COLUMN video_height INTEGER');
    if (!in_array('media_mtime', $columns, true)) $db->exec('ALTER TABLE videos ADD COLUMN media_mtime INTEGER');
    if (!in_array('media_checked', $columns, true)) $db->exec('ALTER TABLE videos ADD COLUMN media_checked INTEGER NOT NULL DEFAULT 0');
    if (!in_array('thumbnail_key', $columns, true)) $db->exec('ALTER TABLE videos ADD COLUMN thumbnail_key TEXT');

    if (!in_array('page_size', $settingsColumns, true)) $db->exec('ALTER TABLE app_settings ADD COLUMN page_size INTEGER NOT NULL DEFAULT 50');
    if (!in_array('fill_last_row', $settingsColumns, true)) $db->exec('ALTER TABLE app_settings ADD COLUMN fill_last_row INTEGER NOT NULL DEFAULT 0');
    if (!in_array('scroll_to_player', $settingsColumns, true)) $db->exec('ALTER TABLE app_settings ADD COLUMN scroll_to_player INTEGER NOT NULL DEFAULT 1');
    if (!in_array('thumbnail_time', $settingsColumns, true)) $db->exec('ALTER TABLE app_settings ADD COLUMN thumbnail_time INTEGER NOT NULL DEFAULT 10');
    if (!in_array('thumbnail_offset', $columns, true)) $db->exec('ALTER TABLE videos ADD COLUMN thumbnail_offset INTEGER');

    if (!in_array('untagged_style', $settingsColumns, true)) $db->exec("ALTER TABLE app_settings ADD COLUMN untagged_style TEXT NOT NULL DEFAULT '{}'");
    $playlistColumns = array_column($db->query('PRAGMA table_info(playlists)')->fetchAll(), 'name');
    if (!in_array('text_color', $playlistColumns, true)) {
        $db->exec("ALTER TABLE playlists ADD COLUMN text_color TEXT NOT NULL DEFAULT '#ffffff'");
        $db->exec("ALTER TABLE playlists ADD COLUMN background_color TEXT NOT NULL DEFAULT '#6c757d'");
        $db->exec("ALTER TABLE playlists ADD COLUMN font TEXT NOT NULL DEFAULT 'system-ui'");
        $db->exec('ALTER TABLE playlists ADD COLUMN font_size INTEGER NOT NULL DEFAULT 12');
        $db->exec('ALTER TABLE playlists ADD COLUMN cover_video TEXT');
    }
    if (!in_array('thumbnail_custom_time', $columns, true)) $db->exec('ALTER TABLE videos ADD COLUMN thumbnail_custom_time INTEGER');
    $db->exec('PRAGMA user_version = ' . $schemaVersion);
    $db->exec('COMMIT');
    $schemaTransaction = false;
} catch (Throwable $error) {
    if ($schemaTransaction) {
        $db->exec('ROLLBACK');
    }

    throw $error;
} finally {
    $db->exec('PRAGMA busy_timeout = 5000');
}
