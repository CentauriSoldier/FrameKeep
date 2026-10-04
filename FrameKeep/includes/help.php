<div class="modal fade" id="help-dialog" tabindex="-1" aria-labelledby="help-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="help-title">FrameKeep help</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="text-body-secondary">Find a topic below or search this guide. The gear opens Settings; the question mark brings you back here.</p>
                <label class="visually-hidden" for="help-search">Search help</label><input class="form-control mb-3" id="help-search" type="search" placeholder="Search help: tags, offline, backups…">
                <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Help topics">
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-start">Getting started</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-libraries">Library locations and offline drives</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-browse">Finding and selecting videos</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-playback">Playback and remembering your place</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-tags">Creating, styling, and assigning tags</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-filtering">Filtering by tags</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-bulk">Bulk tagging</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-playlists">Playlists and playlist sequences</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-scanning">Scanning, moved files, and missing records</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-thumbnails">Thumbnails and appearance</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-backups">Database backups and restoration</button>
                        <button class="btn btn-sm btn-outline-secondary text-start" type="button" data-help-jump="help-deletion">Permanent deletion versus removing membership</button>
                </nav>
                <p class="text-body-secondary d-none" id="help-empty" role="status">No help sections match. Try a different word or choose a topic above.</p>
                <div class="d-grid gap-4">
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-start">
                        <h3 class="h5 mb-3" id="help-start">Getting started</h3>
                        <p>FrameKeep organizes videos with tags and playlists while leaving them in their existing folders. It stores file locations and library metadata in a shared database.</p>
                        <ol><li>Open the gear icon and find <strong>Libraries</strong>.</li><li>Add a name and folder path. <strong>Browse</strong> opens FrameKeep’s folder picker; <strong>Use this folder</strong> fills the path field. You can also type a local or network path manually.</li><li>Click <strong>Add library</strong>, then close Settings and click <strong>Rescan</strong> to import videos from that folder and its subfolders.</li><li>Click a thumbnail, title, or <strong>Play</strong> button to start watching.</li></ol>
                        <p>Settings options save automatically. Checkboxes and dropdowns save when changed; numeric fields save after you finish editing and leave the field, provided the values are valid. The dialog shows saving status. Close does not undo saved changes. New library names and paths are saved only when you click Add library; restore, library removal, and cache clearing remain explicit actions.</p>
                        <p>Folder names do not assign genres or tags. Newly imported videos are Untagged. Changing a display name never renames the disk file.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-libraries">
                        <h3 class="h5 mb-3" id="help-libraries">Library locations and offline drives</h3>
                        <p>Add separate library locations in Settings. Paths must be readable by the computer or NAS running PHP, not merely by the computer displaying the page. Locations cannot overlap an existing library folder.</p>
                        <ul><li><strong>Enabled:</strong> the location participates in scanning and playback. Disable it to pause access while preserving its records.</li><li><strong>Offline:</strong> the root folder cannot be accessed. Video cards show an offline indicator, and their records, tags, and playlist memberships are preserved.</li><li><strong>Incomplete:</strong> a subfolder or file could not be checked. The scan preserves existing missing-file states for that location.</li><li><strong>Remove library — keep files:</strong> removes the location, video records, video-specific tags and names, and playlist entries. Disk files, global tag definitions, playlists, and settings stay intact. To rebuild the library, add the location again and rescan.</li></ul>
                        <p>An accessible location with an absent file produces a missing-video record. An inaccessible location is treated as offline instead. Automatic record cleanup pauses while any location is offline, disabled, or incompletely scanned.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-browse">
                        <h3 class="h5 mb-3" id="help-browse">Finding and selecting videos</h3>
                        <p><strong>Search videos</strong> searches display names. Tag filtering is separate and combines with the name search. Choose name order or playlist order using the sort menu.</p>
                        <p>Use <strong>Videos per page</strong> below the grid to choose 10, 25, 50, 100, 250, or 500. The count is exact unless <strong>Fill last row</strong> is checked. That option rounds up to a full row based on the current grid width; the actual per-page count is shown. The final page contains only the remaining results. Both preferences save automatically.</p>
                        <p>Use First, Previous, Next, and Last, type a page number and press Enter or leave the field, or drag the page slider to navigate. Page numbers are limited to the available pages. Changing page size returns to page one. Refreshing remembers the last page in this browser; if fewer pages remain, it uses the last available page.</p>
                        <p>Rate videos from zero to five stars on library cards or beneath the player. New videos start at zero (Unrated). Click the currently selected star again, or Clear, to remove the rating. Rating changes save immediately and do not scroll the page.</p>
                        <p>Cards, the player, and Details show duration and resolution. FrameKeep reads these in small background batches using FFprobe; unknown values remain visible when inspection fails. Duration and resolution filters only match known values, with separate Unknown choices. Use the sort menu for rating, duration, or resolution order. Scanning resets cached media details when a file’s modification time changes.</p>
                        <p>Card checkboxes select videos for batch actions. <strong>Select results</strong> selects all matching results across pages. <strong>Clear selection</strong> unchecks them. <strong>Play results</strong> queues the available matching videos.</p>
                        <p><strong>Edit</strong> changes a video’s display name, tags, and playlist memberships. <strong>Details</strong> shows its actual filename, path, file size, tags, playlists, and available timestamps. Some filesystems do not supply a creation date.</p>
                        <p><strong>Browse</strong> is available on video cards and library locations; the main player labels this action <strong>Location</strong>. On the local Windows host it can open Explorer, selecting the video when applicable. Otherwise it shows a copyable path. Remote browsers cannot launch Explorer on the hosting computer.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-playback">
                        <h3 class="h5 mb-3" id="help-playback">Playback and remembering your place</h3>
                        <p>Clicking a library thumbnail or title plays the video and, by default, scrolls to the main player. Disable <strong>Scroll to player on title or thumbnail click</strong> in Settings → Playback to stay where you are. Play buttons, selection checkboxes, other actions, and blank card areas do not trigger this scroll.</p>
                        <p>Use the video’s native controls for seeking, volume, mute, and fullscreen. FrameKeep also provides previous, play/pause, next, shuffle, and repeat controls.</p>
                        <ul><li><strong>Repeat Off:</strong> stop at the end of the queue.</li><li><strong>Repeat Video:</strong> repeat the current video.</li><li><strong>Repeat Playlist / sequence:</strong> loop the current queue.</li></ul>
                        <p>Expand <strong>Playback queue</strong> to see or choose queued videos. Unavailable videos are skipped when advancing.</p>
                        <p>Playback position is saved roughly every ten seconds and when pausing, seeking, or leaving. Volume and mute changes are saved too. Reopening restores the last video and position. <strong>Autoplay on open</strong> controls whether it attempts to start automatically; browsers may still require a Play click.</p>
                        <p>Both computers must open the same hosted FrameKeep site to share playback memory. The most recently opened browser session owns saving, so an older session cannot overwrite newer progress. Shuffle, repeat, and playlist-sequence preferences are stored per browser.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-tags">
                        <h3 class="h5 mb-3" id="help-tags">Creating, styling, and assigning tags</h3>
                        <p>Use the tag icon beside <strong>Tags</strong> to create or manage tags. Tags can have their own background color, text color, font, and font size, with a preview while editing.</p>
                        <p>Below the main player, check or uncheck a tag to apply or remove it immediately. Library cards show assigned tags without displaying every possible tag. <strong>Edit</strong> also offers tag assignments.</p>
                        <p><strong>Untagged</strong> means no tags are assigned; it is not a separate tag to manage. Removing the last assignment makes the video Untagged automatically. Deleting a global tag removes its assignments without deleting videos.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-filtering">
                        <h3 class="h5 mb-3" id="help-filtering">Filtering by tags</h3>
                        <p>Each sidebar tag slider has three positions. Click a position, drag the handle, or focus it and use the arrow keys.</p>
                        <ul><li><strong>Left — Exclude:</strong> hide videos carrying this tag.</li><li><strong>Center — Ignore:</strong> this tag does not affect the results.</li><li><strong>Right — Include:</strong> require this tag.</li></ul>
                        <p>With several included tags, a video must have <strong>all</strong> of them. Any excluded tag rules it out. For example, include Synthwave and exclude Ambient to see Synthwave videos without Ambient.</p>
                        <p><strong>Untagged only</strong> finds videos that still need tagging. <strong>Clear tag filters</strong> clears tag restrictions. Search text and playlist selection remain separate controls.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-bulk">
                        <h3 class="h5 mb-3" id="help-bulk">Bulk tagging</h3>
                        <ol><li>Select videos using card checkboxes or <strong>Select results</strong>.</li><li>Click <strong>Tag selected</strong>.</li><li>Choose <strong>Add chosen tags</strong> or <strong>Remove chosen tags</strong>, then check the tags to change.</li><li>Click <strong>Apply</strong>.</li></ol>
                        <p>Only the chosen tags and selected videos are affected. Other assignments stay as they were. Removing all assignments returns a video to Untagged. Create a global tag first if the tag you need does not exist yet.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-playlists">
                        <h3 class="h5 mb-3" id="help-playlists">Playlists and playlist sequences</h3>
                        <p>Use the plus button beside <strong>Playlists</strong> to create, rename, or delete a playlist. Select a playlist in the sidebar to view its videos. <strong>All videos</strong> returns to the complete library.</p>
                        <p>Select videos and click <strong>Add / move to playlist</strong>. Add keeps existing memberships; Move removes membership from the current source playlist and adds the destination. Videos can belong to multiple playlists. None of these operations move disk files.</p>
                        <p>Within a playlist, use the up/down buttons to change order or <strong>Remove</strong> to remove membership. Deleting a playlist removes its entries but keeps the videos.</p>
                        <p>Open <strong>Playlist sequence</strong>, append playlists in the desired order, and click <strong>Play sequence</strong>. This combines them into one playback queue; shuffle and repeat apply to that queue.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-scanning">
                        <h3 class="h5 mb-3" id="help-scanning">Scanning, moved files, and missing records</h3>
                        <p>In Settings, <strong>Scan library on start</strong> controls the opening scan. <strong>Automatic periodic scanning</strong> and <strong>Scan interval (seconds)</strong> control later scans while the page remains open. The initial interval is 300 seconds. Manual <strong>Rescan</strong> remains available.</p>
                        <p>System messages show the current scan phase, file, and counts. During discovery, the total is not yet known. Active scan and thumbnail messages clear when their tasks finish, returning to Library ready.</p>
                        <p>Scans search every enabled location recursively. A unique matching filename and recorded size can reconnect a moved file automatically, preserving its permanent record, tags, playlists, and edited name. Automatic matching waits until all locations are fully accessible.</p>
                        <p>The <strong>Missing files</strong> sidebar lists unresolved records with candidate paths. A same-size file with a different name is labeled as a possible rename, not a proven match. Click <strong>Use this file</strong> only when it is the right replacement. Files already assigned to another video record are not offered as replacements.</p>
                        <p><strong>Remove library record</strong> beneath a missing video manually removes that entry after confirmation. Its edited name, tag assignments, and playlist memberships are removed; disk files and global tag definitions remain. Removal is refused if the file has reappeared or the library is unavailable.</p>
                        <p><strong>Automatically remove missing records</strong> is off by default. When enabled, it removes a missing record only after a complete scan finds no video with its filename or recorded size. Unknown-size records and unavailable libraries are preserved. Cleanup removes the record’s tags and playlist entries; it does not delete disk files.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-thumbnails">
                        <h3 class="h5 mb-3" id="help-thumbnails">Thumbnails and appearance</h3>
                        <p>Settings offers Small, Medium, and Large thumbnail sizes. Larger previews put fewer videos in each row. Previews remain square and titles wrap below them.</p>
                        <p><strong>Thumbnail time (seconds)</strong> chooses the frame position for all previews, starting at 10 seconds. Choose any whole number from 0 to 86400. Videos shorter than that position fall back to their first frame. Changing the position generates new previews as they are viewed.</p>
                        <p>Thumbnail totals show ready, pending, and cached images; videos awaiting a scan are counted separately. Cached images can include previews from previous time settings.</p>
                        <p>Thumbnails are generated as needed and cached separately from the videos. <strong>Clear thumbnail cache</strong> removes generated images; viewing videos regenerates them. It preserves videos, tags, playlists, and display names.</p>
                        <p><strong>Generate all thumbnails now</strong> in Settings processes all available videos one at a time, including other pages, and reuses cached previews. This can use substantial CPU and disk activity. Keep the page open; closing it ends the job. <strong>Stop after current thumbnail</strong> stops further requests once the active request finishes. Progress reports failed previews so you can retry. Normal generation as cards load remains unchanged.</p>
                        <p>If a thumbnail cannot be generated, a placeholder may appear. Video playback also depends on the format and codecs supported by your browser. A playback warning does not by itself mean the disk file has been deleted.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-backups">
                        <h3 class="h5 mb-3" id="help-backups">Database backups and restoration</h3>
                        <p>Under Settings → <strong>Database backups</strong>, click <strong>Download database backup</strong> to save a snapshot of video records, library locations, tags, playlists, settings, and playback memory. Video files and cached thumbnails are not included.</p>
                        <ol><li>Choose a FrameKeep database backup using <strong>Restore database backup</strong>.</li><li>Click <strong>Restore backup</strong> and confirm replacement of the current metadata.</li><li>After restoration, use the recovery-snapshot download link if needed, then click <strong>Reload FrameKeep</strong>.</li></ol>
                        <p>Changes made after the chosen backup are replaced. A snapshot of the current database is saved before restoration. Invalid or inconsistent backups are rejected without committing changes. Restoring metadata does not move, replace, or recover video files.</p>
                    </section>
                    <section class="border rounded p-3" data-help-section aria-labelledby="help-deletion">
                        <h3 class="h5 mb-3" id="help-deletion">Permanent deletion versus removing membership</h3>
                        <p><strong>Delete</strong> on a video card and <strong>Delete selected</strong> permanently remove the actual video files from disk after confirmation. They also remove video records and their tag and playlist assignments. There is no undo or recycle-bin step.</p>
                        <p>Use <strong>Details</strong> to check filename, path, and size before deleting. Bulk deletion reports failures; some files may succeed while others remain.</p>
                        <p><strong>Remove</strong> within a playlist affects membership only. <strong>Remove library — keep files</strong> removes a location and its database entries only. Database backups recover metadata, not files deleted from disk.</p>
                    </section>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
