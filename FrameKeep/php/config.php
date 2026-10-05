<?php

require_once __DIR__ . '/log.php';

define('D_ROOT',        $_SERVER['DOCUMENT_ROOT']);
define('D_PHP',         D_ROOT      . '/php');
define('D_INCLUDES',    D_ROOT      . '/includes');
define('D_DATA',        D_ROOT      . '/data');
define('D_ASSETS',      D_ROOT      . '/assets');
define('D_CSS',         D_ASSETS    . '/css');
define('D_JS',          D_ASSETS    . '/js');
define('D_FONTS',       D_CSS       . '/fonts');
define('D_BACKUPS',     D_DATA      . '/backups');
define('D_THUMBNAILS',  D_DATA      . '/thumbnails');

define('F_LOCAL_CONFIG', D_DATA . '/config.php');
if (!is_dir(D_DATA) && !mkdir(D_DATA, 0775, true) && !is_dir(D_DATA)) throw new RuntimeException('Could not create the data folder.');
if (is_file(F_LOCAL_CONFIG)) require_once F_LOCAL_CONFIG;
if (!defined('D_VIDEOS')) define('D_VIDEOS', '');
if (!defined('F_FFMPEG')) define('F_FFMPEG', '');
if (!defined('F_FFPROBE')) define('F_FFPROBE', '');

define('F_CONFIG',                  D_PHP   . '/config.php');
define('F_KICKSTART',               D_PHP   . '/kickstart.php');
define('F_SCHEMA',                  D_PHP   . '/schema.php');
define('F_SCANNER',                 D_PHP   . '/scanner.php');
define('F_PROGRESS',                D_DATA  . '/scan-progress.json');
define('F_PROGRESS_API',            D_PHP   . '/progress.php');
define('F_RESTORE',                 D_PHP   . '/restore.php');
define('F_BACKUP',                  D_PHP   . '/backup.php');
define('F_THUMBNAIL_LOCK',          D_DATA  . '/thumbnail.lock');
define('F_SCAN_LOCK',               D_DATA  . '/scan.lock');
define('F_FUNCTIONS',               D_PHP   . '/functions.php');
define('F_API',                     D_PHP   . '/api.php');
define('F_STREAM',                  D_PHP   . '/stream.php');
define('F_THUMBNAIL',               D_PHP   . '/thumbnail.php');
define('F_APP_JS',                  D_JS        . '/framekeep.js');
define('F_APP_CSS',                 D_CSS       . '/framekeep.css');
define('F_HELP',                    D_INCLUDES  . '/help.php');
define('F_DIALOGS',                 D_INCLUDES  . '/dialogs.php');
define('F_INDEX',                   D_ROOT      . '/index.php');
define('F_HEADER',                  D_INCLUDES  . '/header.php');
define('F_NAV',                     D_INCLUDES  . '/nav.php');
define('F_FOOTER',                  D_INCLUDES  . '/footer.php');
define('F_SIDEBAR',                 D_INCLUDES  . '/sidebar.php');
define('F_PLAYER',                  D_INCLUDES  . '/player.php');
define('F_LIBRARY',                 D_INCLUDES  . '/library.php');
define('F_DATA',                    D_DATA      . '/data.db');
define('F_BOOTSTRAP_CSS',           D_CSS       . '/bootstrap.min.css');
define('F_BOOTSTRAP_JS',            D_JS        . '/bootstrap.bundle.min.js');
define('F_BOOTSTRAP_ICONS_CSS',     D_CSS       . '/bootstrap-icons.min.css');
define('F_BOOTSTRAP_ICONS_WOFF',    D_FONTS     . '/bootstrap-icons.woff');
define('F_BOOTSTRAP_ICONS_WOFF2',   D_FONTS     . '/bootstrap-icons.woff2');

define('APP_VERSION',       '0.3');

define('SCAN_FREQUENCY',    300);

define('VIDEO_EXTENSIONS',    array('mp4', 'm4v', 'webm', 'ogv', 'mov', 'mkv', 'avi'));
