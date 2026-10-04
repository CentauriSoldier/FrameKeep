<aside class="col-12 col-lg-3" aria-label="Library filters">
    <div class="system-bar mb-3 py-2 px-3 border rounded" aria-label="FrameKeep and system messages">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-play-circle text-info" aria-hidden="true"></i>
            <span class="fw-semibold">FrameKeep <small class="text-body-secondary">v<?= htmlspecialchars(APP_VERSION, ENT_QUOTES, 'UTF-8') ?></small></span>
            <button class="btn btn-sm btn-outline-secondary ms-auto" id="open-help" type="button" aria-label="Help" title="How to use FrameKeep"><i class="bi bi-question-circle" aria-hidden="true"></i></button>
            <button class="btn btn-sm btn-outline-secondary" id="open-settings" type="button" aria-label="Settings" title="Settings"><i class="bi bi-gear" aria-hidden="true"></i></button>
        </div>
        <div class="mt-2 text-break">
            <div class="small text-body-secondary" id="scan-status" role="status">Loading library…</div>
            <div class="small text-info d-none mt-1" id="scan-activity" role="status"></div>
            <div class="small text-info d-none mt-1" id="thumbnail-activity" role="status"></div>
            <div class="small text-body-secondary mt-1" id="thumbnail-count" role="status"></div>
            <div id="app-message" class="small text-danger d-none mt-1" role="alert"></div>
        </div>
    </div>

    <section class="card mb-4" aria-labelledby="playlists-heading">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0" id="playlists-heading">Playlists</h2>
            <button class="btn btn-sm btn-outline-secondary" id="manage-playlists" type="button" aria-label="Manage playlists" title="Manage playlists"><i class="bi bi-sliders" aria-hidden="true"></i></button>
        </div>
        <div class="card-body">
            <button class="btn btn-outline-info w-100 text-start mb-2" id="all-videos" type="button"><i class="bi bi-collection-play me-2" aria-hidden="true"></i>All videos</button>
            <div id="playlist-list" class="d-grid gap-2"></div>
            <button class="btn btn-sm btn-outline-secondary w-100 mt-3" id="open-sequence" type="button"><i class="bi bi-music-note-list me-2" aria-hidden="true"></i>Playlist sequence</button>
        </div>
    </section>
    <section class="card" aria-labelledby="tags-heading">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0" id="tags-heading">Tags</h2>
            <button class="btn btn-sm btn-outline-secondary" id="manage-tags" type="button" aria-label="Manage tags" title="Manage tags"><i class="bi bi-tags" aria-hidden="true"></i></button>
        </div>
        <div class="card-body">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="untagged-only">
                <label class="form-check-label" for="untagged-only">Untagged only</label>
            </div>
            <div id="tag-filters" class="d-grid gap-2"></div>
            <button class="btn btn-sm btn-link px-0 mt-2" type="button" id="clear-filters">Clear tag filters</button>
        </div>
    </section>
    <section class="card mt-4" aria-labelledby="missing-heading">
        <div class="card-header"><h2 class="h6 mb-0" id="missing-heading">Missing files</h2></div>
        <div class="card-body d-grid gap-3" id="missing-files"></div>
    </section>
</aside>
