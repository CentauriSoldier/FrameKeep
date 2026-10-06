    <section class="card mb-4" aria-labelledby="tags-heading">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0" id="tags-heading"><button class="btn btn-sm btn-link text-body text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#tags-content" aria-expanded="true" aria-controls="tags-content">Tags <i class="bi bi-chevron-down" aria-hidden="true"></i></button></h2>
            <div class="d-flex flex-wrap gap-2"><button class="btn btn-sm btn-outline-secondary" id="reset-tag-filters" type="button" title="Return all tag filters to Ignore without changing video tags.">Reset tag filters</button><button class="btn btn-sm btn-outline-secondary" id="open-tag-help" type="button" aria-label="Tag filter help" title="Tag filter help" data-bs-toggle="modal" data-bs-target="#tag-help-dialog"><i class="bi bi-question-circle" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-secondary" id="manage-tags" type="button" aria-label="Manage tags" title="Manage tags"><i class="bi bi-tags" aria-hidden="true"></i></button></div>
        </div>
        <div class="collapse show" id="tags-content"><div class="card-body">
            <div id="tag-filters" class="d-grid gap-2"></div>
            <div class="d-none"><input class="form-check-input" type="radio" name="solo-tag" id="untagged-only" aria-label="Show only untagged videos"></div>
        </div>
    </div></section>
<section class="card" aria-labelledby="library-heading">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
        <h2 class="h6 mb-0" id="library-heading">Video library</h2>
        <button class="btn btn-sm btn-outline-secondary" id="rescan" type="button"><i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>Rescan</button>
    </div>
    <div class="library-status px-3 py-2 border-bottom text-break" aria-label="Library status">
        <div class="small text-body-secondary" id="scan-status" role="status">Loading library…</div>
        <div class="small text-info d-none mt-1" id="scan-activity" role="status"></div>
        <div class="small text-info d-none mt-1" id="thumbnail-activity" role="status"></div>
        <div class="small text-body-secondary mt-1" id="thumbnail-count" role="status"></div>
        <div id="app-message" class="small text-danger d-none mt-1" role="alert"></div>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-12 col-md"><label class="visually-hidden" for="video-search">Search videos</label><div class="input-group"><button class="btn btn-outline-secondary" id="clear-video-search" type="button" aria-label="Clear video search" title="Clear search"><i class="bi bi-x-lg" aria-hidden="true"></i></button><input class="form-control" type="search" id="video-search" placeholder="Search videos"></div></div>
            <div class="col-12 col-md-auto"><label class="visually-hidden" for="video-sort">Sort videos</label><select class="form-select" id="video-sort"><option value="default">Playlist order / name</option><option value="asc">Name A–Z</option><option value="desc">Name Z–A</option><option value="rating">Rating high–low</option><option value="duration">Duration short–long</option><option value="resolution">Resolution high–low</option></select></div>
        </div>
        <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <div><label class="form-label small" for="attention-filter">Attention</label><select id="attention-filter" class="form-select form-select-sm"><option value="exclude">Exclude</option><option value="only">Only</option></select></div>
            <div><label class="form-label small" for="rating-filter">Rating</label><select id="rating-filter" class="form-select form-select-sm"><option value="all">Any</option><option value="0">Unrated</option><option value="1">1+</option><option value="2">2+</option><option value="3">3+</option><option value="4">4+</option><option value="5">5</option></select></div>
            <div><label class="form-label small" for="duration-filter">Duration (mins)</label><select id="duration-filter" class="form-select form-select-sm"><option value="all">Any</option><option value="short">&lt;10</option><option value="medium">10–60</option><option value="long">60+</option><option value="unknown">Unknown</option></select></div>
            <div><label class="form-label small" for="resolution-filter">Resolution</label><select id="resolution-filter" class="form-select form-select-sm"><option value="all">Any</option><option value="720">720p+</option><option value="1080">1080p+</option><option value="2160">2160p+</option><option value="unknown">Unknown</option></select></div>
            <button class="btn btn-sm btn-outline-secondary" id="clear-library-filters" type="button">Clear filters</button>
            <button class="btn btn-sm btn-outline-secondary" id="clear-search-filters" type="button">Clear search &amp; filters</button>
        <div class="d-flex flex-wrap align-items-end gap-2 ms-auto">
            <div role="group" aria-label="Results"><div class="form-label small">Results</div><div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="play-results" type="button">Play</button>
                <button class="btn btn-sm btn-outline-secondary" id="select-results" type="button">Select</button>
            </div></div>
            <div class="d-flex flex-wrap align-items-center gap-2 border-start ps-3 ms-1" role="group" aria-label="Selection actions">
                <span class="small text-body-secondary">Selection</span>
                <button class="btn btn-sm btn-outline-secondary" id="batch-playlist" type="button" disabled>Add / move to playlist</button>
                <button class="btn btn-sm btn-outline-secondary" id="batch-tags" type="button" disabled>Tag</button>
                <button class="btn btn-sm btn-outline-secondary" id="clear-selection" type="button">Clear</button>
                <button class="btn btn-sm btn-outline-danger" id="delete-selected" type="button" disabled>Delete</button>
            </div>
        </div>
        </div>
        <p id="library-count" class="small text-body-secondary" role="status"></p>
        <div id="video-grid"></div>
        <nav class="d-grid gap-2 mt-4" aria-label="Library pages">
            <div class="d-flex flex-wrap align-items-center justify-content-center gap-3">
                <label class="small" for="videos-per-page">Videos per page</label><select class="form-select form-select-sm w-auto" id="videos-per-page"><option>10</option><option>25</option><option>50</option><option>100</option><option>250</option><option>500</option></select>
                <label class="form-check mb-0"><input class="form-check-input" type="checkbox" id="fill-last-row" title="Round the selected count up to fill the last row when enough videos remain."><span class="form-check-label small">Fill last row</span></label><span id="page-size-status" class="small text-body-secondary"></span>
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-center gap-2"><button class="btn btn-sm btn-outline-secondary" id="first-page" type="button">First</button><button class="btn btn-sm btn-outline-secondary" id="previous-page" type="button">Previous</button><label class="small" for="page-number">Page</label><input class="form-control form-control-sm" style="width: 6rem" id="page-number" type="number" min="1" step="1"><span id="page-status" class="small text-body-secondary"></span><button class="btn btn-sm btn-outline-secondary" id="next-page" type="button">Next</button><button class="btn btn-sm btn-outline-secondary" id="last-page" type="button">Last</button></div>
            <label class="visually-hidden" for="page-slider">Choose library page</label><input class="form-range" id="page-slider" type="range" min="1" max="1" step="1">
        </nav>
    </div>
</section>
