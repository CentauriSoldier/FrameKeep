<div class="modal fade" id="settings-dialog" tabindex="-1" aria-labelledby="settings-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" id="settings-form">
            <div class="modal-header"><h2 class="modal-title fs-5" id="settings-title">Settings</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="small text-body-secondary mb-4">Settings apply to every browser using this library.</p>
                <div class="d-grid gap-4">
                <section class="border rounded p-3" aria-labelledby="settings-libraries-heading">
                    <h3 class="h6 mb-3" id="settings-libraries-heading">Libraries</h3>
                    <div id="library-locations" class="d-grid gap-2 mb-3"></div>
                    <hr class="my-3">
                    <h4 class="h6 mb-3">Add a library</h4>
                    <label class="form-label" for="library-name">New library name</label><input class="form-control mb-2" id="library-name" placeholder="External drive">
                    <label class="form-label" for="library-path">Folder path</label><div class="input-group mb-2"><input class="form-control" id="library-path" placeholder="Full local or network path"><button class="btn btn-outline-secondary" id="browse-library-path" type="button">Browse</button></div>
                    <div class="border rounded p-2 mb-2 d-none" id="folder-picker">
                        <p class="small text-body-secondary mb-2">Folders on the FrameKeep host</p>
                        <div class="d-flex gap-2 mb-2"><button class="btn btn-sm btn-outline-secondary" id="folder-roots" type="button">Locations</button><button class="btn btn-sm btn-outline-secondary" id="folder-up" type="button" disabled>Up</button></div>
                        <div id="folder-current" class="small text-break mb-2" role="status"></div>
                        <div id="folder-list" class="d-grid gap-1 overflow-auto" style="max-height: 16rem;"></div>
                        <div class="d-flex gap-2 mt-2"><button class="btn btn-sm btn-info" id="choose-folder" type="button" disabled>Use this folder</button><button class="btn btn-sm btn-secondary" id="cancel-folder" type="button">Cancel</button></div>
                    </div>
                    <button class="btn btn-outline-info" id="add-library" type="button">Add library</button>
                    <p class="small text-body-secondary mt-2 mb-0">Locations scan recursively. Disable a location to pause access without removing its videos, tags, or playlists.</p>
                </section>
                <section class="border rounded p-3" aria-labelledby="settings-playback-heading">
                    <h3 class="h6 mb-3" id="settings-playback-heading">Playback</h3>
                    <label class="form-check mb-3"><input class="form-check-input" type="checkbox" id="scroll-to-player" data-help="Scroll to the main player when you click a library video’s title or thumbnail. Other buttons and selections do not scroll."><span class="form-check-label">Scroll to player on title or thumbnail click</span></label>
                    <label class="form-check mb-0"><input class="form-check-input" type="checkbox" id="settings-autoplay" data-help="Try to play the last video automatically at its saved position. Your browser may require you to press Play."><span class="form-check-label">Autoplay on open</span></label>
                </section>
                <section class="border rounded p-3" aria-labelledby="settings-scanning-heading">
                    <h3 class="h6 mb-3" id="settings-scanning-heading">Scanning</h3>
                    <label class="form-check"><input class="form-check-input" type="checkbox" id="scan-on-start" data-help="Scan your video folders when FrameKeep opens. Turn this off to load the saved library without an initial scan."><span class="form-check-label">Scan library on start</span></label>
                    <label class="form-check mt-2"><input class="form-check-input" type="checkbox" id="auto-scan" data-help="Check for new, moved, or missing videos periodically while this page remains open."><span class="form-check-label">Automatic periodic scanning</span></label>
                    <label class="form-check mt-2"><input class="form-check-input" type="checkbox" id="auto-orphan" data-help="After a complete scan, remove missing video records only when no scanned video shares their filename or recorded size. This removes tags and playlist memberships for those records, not video files. Unknown-size records and incomplete scans are kept."><span class="form-check-label">Automatically remove missing records</span></label>
                    <label class="form-label mt-3" for="scan-frequency">Scan interval (seconds)</label>
                    <input class="form-control" type="number" id="scan-frequency" data-help="Seconds between automatic scans. This does not change the startup scan or manual Rescan button." min="1" max="86400" required>
                </section>
                <section class="border rounded p-3" aria-labelledby="settings-thumbnails-heading">
                    <h3 class="h6 mb-3" id="settings-thumbnails-heading">Thumbnails</h3>
                    <label class="form-label" for="thumbnail-size">Thumbnail size</label>
                    <select class="form-select" id="thumbnail-size" data-help="Choose how large library previews appear. Larger previews mean fewer videos per row; video files are unchanged."><option value="small">Small</option><option value="medium">Medium</option><option value="large">Large</option></select>
                    <label class="form-label mt-3" for="thumbnail-time">Thumbnail time (seconds)</label>
                    <input class="form-control" id="thumbnail-time" type="number" min="0" max="86400" step="1" required data-help="Default time for videos without a custom thumbnail time. Custom choices are preserved. New previews use this position as videos are viewed. For shorter clips, the first frame is used if that position produces no image.">
                    <button class="btn btn-outline-secondary mt-3" id="clear-thumbnails" data-help="Delete generated preview images only. They regenerate as videos are viewed. Custom thumbnail times, videos, tags, playlists, and display names are preserved." type="button">Clear thumbnail cache</button>
                    <button class="btn btn-outline-danger mt-3" id="reset-thumbnail-times" type="button" data-help="Remove all custom thumbnail times. Every video will use the global default; previews regenerate as needed.">Reset all thumbnail times</button>
                    <div class="border-top mt-3 pt-3">
                        <p class="small text-body-secondary">Normally, previews are created as you view library cards. Generate all thumbnails now processes all available videos one at a time and reuses cached previews. This can use substantial NAS CPU and disk activity. Keep this page open until it finishes.</p>
                        <button class="btn btn-outline-info" id="generate-thumbnails" type="button" data-help="Create previews for all available library videos, including those on other pages. Uses CPU and disk activity; keep the page open. Cached images are reused.">Generate all thumbnails now</button>
                        <button class="btn btn-outline-secondary" id="stop-thumbnails" type="button" hidden>Stop after current thumbnail</button>
                        <p class="small text-body-secondary mt-2 mb-0" id="bulk-thumbnail-status" role="status"></p>
                    </div>
                </section>
                <section class="border rounded p-3" aria-labelledby="settings-backups-heading">
                    <h3 class="h6 mb-3" id="settings-backups-heading">Database backups</h3>
                    <p class="small text-body-secondary">Backups contain library metadata, not video files.</p>
                    <a class="btn btn-outline-info" id="backup-database" data-help="Download a snapshot of library metadata, including tags, playlists, display names, settings, and saved playback. Video files and thumbnails are not included.">Download database backup</a>
                    <div class="border-top mt-3 pt-3"><label class="form-label" for="restore-file">Restore database backup</label><input class="form-control" id="restore-file" type="file" accept=".db,.sqlite,.sqlite3"><button class="btn btn-outline-warning mt-2" id="restore-database" type="button" data-help="Replace the current library metadata with a FrameKeep backup. Video files and thumbnails stay untouched. A recovery snapshot is saved first.">Restore backup</button><p class="small text-body-secondary mt-2 mb-0" id="restore-status" role="status"></p></div>
                </section>
                </div>
            </div>
            <div class="modal-footer"><span class="small text-danger me-auto dialog-error" role="alert"></span><span class="small text-body-secondary" id="settings-save-status" role="status">Options save automatically.</span><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
        </form>
    </div>
</div>
<div class="modal fade" id="attention-dialog" tabindex="-1" aria-labelledby="attention-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="attention-title">File needing attention</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p class="fw-semibold text-break" id="attention-name"></p><p id="attention-reasons"></p><p class="small text-body-secondary">An empty file contains no video data. Unavailable duration or resolution means inspection did not obtain that information; it does not prove the file is damaged. Check playback and the error log.</p><label class="form-label" for="attention-path">File path</label><textarea class="form-control" id="attention-path" rows="3" readonly></textarea><p class="small mt-2 mb-0" id="attention-copy-status" role="status"></p></div>
        <div class="modal-footer"><button class="btn btn-info" id="copy-attention-path" type="button">Copy path</button><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<div class="modal fade" id="log-dialog" tabindex="-1" aria-labelledby="log-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="log-title">Error log</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p class="small text-body-secondary">Newest events first. Logs may include private file paths.</p><div id="log-events" class="d-grid gap-2"></div><p class="dialog-error text-danger mt-2" role="alert"></p></div>
        <div class="modal-footer"><p class="small w-100 mb-0" id="log-copy-status" role="status"></p><textarea class="form-control w-100" id="log-copy-text" rows="4" readonly hidden aria-label="Log text for manual copying"></textarea><button class="btn btn-outline-secondary" id="copy-all-log" type="button">Copy all</button><button class="btn btn-outline-secondary" id="refresh-log" type="button">Refresh</button><button class="btn btn-outline-danger" id="clear-log" type="button">Clear log</button><a class="btn btn-info" href="php/log_api.php?action=download">Download log</a><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<div class="modal fade" id="tag-help-dialog" tabindex="-1" aria-labelledby="tag-help-title" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="tag-help-title">Tag filters</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p>The tag name stays fixed beneath the sliding control.</p><ul><li>Left: Exclude videos with this tag.</li><li>Center: Ignore this tag when filtering.</li><li>Right: Include videos with this tag.</li><li>Solo: Include this tag and exclude every other tag, showing videos with only this tag.</li></ul><p class="mb-0">Reset tag filters returns all sliders to the center. Filters do not change the tags assigned to your videos.</p></div>
        <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<div class="modal fade" id="assignment-dialog" tabindex="-1" aria-labelledby="assignment-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" id="assignment-form">
        <div class="modal-header"><h2 class="modal-title fs-5" id="assignment-title">Tag video</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p class="fw-semibold" id="assignment-video-name"></p><p class="small text-body-secondary" id="assignment-note"></p><div class="d-grid gap-2" id="assignment-choices"></div></div>
        <div class="modal-footer"><span class="small text-danger me-auto dialog-error" role="alert"></span><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-info" type="submit">Save</button></div>
    </form></div>
</div>

<div class="modal fade" id="details-dialog" tabindex="-1" aria-labelledby="details-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="details-title">Video details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><dl id="file-details" class="row mb-0"></dl><p class="small text-danger dialog-error" role="alert"></p></div>
            <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<div class="modal fade" id="video-dialog" tabindex="-1" aria-labelledby="video-dialog-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" id="video-form">
            <div class="modal-header"><h2 class="modal-title fs-5" id="video-dialog-title">Edit video</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label" for="video-name">Display name</label><input class="form-control mb-3" id="video-name" required>
                <p class="small text-body-secondary text-break" id="video-location"></p>
                <fieldset class="border rounded p-3 mb-3">
                    <legend class="fs-6">Thumbnail</legend>
                    <p class="small text-body-secondary" id="video-thumbnail-current"></p>
                    <video id="video-thumbnail-preview" class="w-100 bg-black rounded" style="max-height: 220px" preload="metadata" muted playsinline aria-label="Thumbnail frame preview"></video>
                    <label class="form-label mt-2" for="video-thumbnail-time">Frame time (seconds)</label>
                    <input class="form-range" id="video-thumbnail-time" type="range" min="0" max="0" step="1" value="0" disabled>
                    <div class="d-flex align-items-center gap-2"><output id="video-thumbnail-position">0 s</output><button class="btn btn-sm btn-outline-info" id="set-video-thumbnail" type="button" disabled>Set thumbnail</button></div>
                    <p class="small text-body-secondary mb-0 mt-2" id="video-thumbnail-status">Select a frame, then Set thumbnail. This saves immediately, independently of the other Edit fields.</p>
                </fieldset>
                <fieldset class="mb-3"><legend class="fs-6">Tags</legend><div id="video-tags" class="d-flex flex-wrap gap-3"></div></fieldset>
                <fieldset><legend class="fs-6">Playlists</legend><div id="video-playlists" class="d-flex flex-wrap gap-3"></div></fieldset>
            </div>
            <div class="modal-footer"><span class="small text-danger me-auto dialog-error" role="alert"></span><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-info" type="submit">Save</button></div>
        </form>
    </div>
</div>
<div class="modal fade" id="manager-dialog" tabindex="-1" aria-labelledby="manager-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="manager-title">Manage tags</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <form id="manager-form" class="d-flex gap-2 mb-3"><label class="visually-hidden" for="manager-name">New name</label><input class="form-control" id="manager-name" placeholder="New name" required><button class="btn btn-info" type="submit">Create</button></form>
                <div id="manager-list" class="d-grid gap-2"></div><p class="small text-danger mt-3 mb-0 dialog-error" role="alert"></p>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="sequence-dialog" tabindex="-1" aria-labelledby="sequence-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="sequence-title">Playlist sequence</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="d-flex gap-2 mb-3"><label class="visually-hidden" for="sequence-playlist">Playlist to append</label><select class="form-select" id="sequence-playlist"></select><button class="btn btn-outline-info" id="sequence-add" type="button">Add</button></div>
                <div id="sequence-list" class="d-grid gap-2"></div><p class="small text-danger mt-3 mb-0 dialog-error" role="alert"></p>
            </div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" id="sequence-clear" type="button">Clear</button><button class="btn btn-info" id="sequence-play" type="button">Play sequence</button></div>
        </div>
    </div>
</div>
<div class="modal fade" id="batch-dialog" tabindex="-1" aria-labelledby="batch-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="batch-form">
            <div class="modal-header"><h2 class="modal-title fs-5" id="batch-title">Add / move to playlist</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p id="batch-count"></p><label class="form-label" for="batch-target">Destination playlist</label><select class="form-select mb-3" id="batch-target" required></select>
                <label class="form-label" for="batch-mode">Action</label><select class="form-select" id="batch-mode"><option value="add">Add to playlist</option><option value="move">Move from current playlist</option></select>
            </div>
            <div class="modal-footer"><span class="small text-danger me-auto dialog-error" role="alert"></span><button class="btn btn-info" type="submit">Apply</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="bulk-tags-dialog" tabindex="-1" aria-labelledby="bulk-tags-title" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" id="bulk-tags-form">
        <div class="modal-header"><h2 class="modal-title fs-5" id="bulk-tags-title">Tag selected videos</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p id="bulk-tags-count" class="small"></p><label class="form-label" for="bulk-tags-mode">Action</label><select class="form-select mb-3" id="bulk-tags-mode"><option value="add">Add chosen tags</option><option value="remove">Remove chosen tags</option></select><div id="bulk-tags-list" class="d-flex flex-wrap gap-3"></div><p class="small text-body-secondary mt-3 mb-0">Other tags remain unchanged. Removing the last tag makes a video Untagged.</p></div>
        <div class="modal-footer"><span class="small text-danger me-auto dialog-error" role="alert"></span><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-info" type="submit">Apply</button></div>
    </form></div>
</div>
<div class="modal fade" id="browse-dialog" tabindex="-1" aria-labelledby="browse-title" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="browse-title">File location</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="small text-body-secondary">Open this location in your file manager. Remote browsers cannot launch the hosting computer's Explorer.</p><input class="form-control" id="browse-path" readonly aria-label="File path"><p class="small mt-2" id="browse-status" role="status"></p></div><div class="modal-footer"><button class="btn btn-info" id="copy-browse-path" type="button">Copy path</button><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div></div></div>
</div>

<?php require_once F_HELP; ?>

<div class="modal fade" id="missing-list-dialog" tabindex="-1" aria-labelledby="missing-heading" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="missing-heading">Missing files</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div id="missing-files" class="d-grid gap-3"></div></div>
        <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<div class="modal fade" id="attention-list-dialog" tabindex="-1" aria-labelledby="attention-heading" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="attention-heading">Attention</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div id="attention-files" class="d-grid gap-3"></div></div>
        <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>
