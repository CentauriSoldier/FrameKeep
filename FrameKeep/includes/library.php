<section class="card" aria-labelledby="library-heading">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
        <h2 class="h6 mb-0" id="library-heading">Video library</h2>
        <button class="btn btn-sm btn-outline-secondary" id="rescan" type="button"><i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>Rescan</button>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-12 col-md"><label class="visually-hidden" for="video-search">Search videos</label><input class="form-control" type="search" id="video-search" placeholder="Search videos"></div>
            <div class="col-12 col-md-auto"><label class="visually-hidden" for="video-sort">Sort videos</label><select class="form-select" id="video-sort"><option value="default">Playlist order / name</option><option value="asc">Name A–Z</option><option value="desc">Name Z–A</option><option value="rating">Rating high–low</option><option value="duration">Duration short–long</option><option value="resolution">Resolution high–low</option></select></div>
        </div>
        <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <div><label class="form-label small" for="rating-filter">Rating</label><select id="rating-filter" class="form-select form-select-sm"><option value="all">All ratings</option><option value="0">Unrated</option><option value="1">1+ stars</option><option value="2">2+ stars</option><option value="3">3+ stars</option><option value="4">4+ stars</option><option value="5">5 stars</option></select></div>
            <div><label class="form-label small" for="duration-filter">Duration</label><select id="duration-filter" class="form-select form-select-sm"><option value="all">Any duration</option><option value="short">Under 10 minutes</option><option value="medium">10–60 minutes</option><option value="long">1 hour or longer</option><option value="unknown">Unknown duration</option></select></div>
            <div><label class="form-label small" for="resolution-filter">Resolution</label><select id="resolution-filter" class="form-select form-select-sm"><option value="all">Any resolution</option><option value="720">720p or higher</option><option value="1080">1080p or higher</option><option value="2160">2160p or higher</option><option value="unknown">Unknown resolution</option></select></div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <button class="btn btn-sm btn-outline-info" id="play-results" type="button">Play results</button>
            <button class="btn btn-sm btn-outline-secondary" id="select-results" type="button">Select results</button>
            <button class="btn btn-sm btn-outline-secondary" id="batch-playlist" type="button" disabled>Add / move to playlist</button>
            <button class="btn btn-sm btn-outline-secondary" id="batch-tags" type="button" disabled>Tag selected</button>
            <button class="btn btn-sm btn-link" id="clear-selection" type="button">Clear selection</button>
            <button class="btn btn-sm btn-outline-danger" id="delete-selected" type="button" disabled>Delete selected</button>
        </div>
        <p id="library-count" class="small text-body-secondary" role="status"></p>
        <div id="video-grid"></div>
        <nav class="d-grid gap-2 mt-4" aria-label="Library pages">
            <div class="d-flex flex-wrap align-items-center justify-content-center gap-3">
                <label class="small" for="videos-per-page">Videos per page</label><select class="form-select form-select-sm w-auto" id="videos-per-page"><option>10</option><option>25</option><option>50</option><option>100</option><option>250</option><option>500</option></select>
                <label class="form-check mb-0"><input class="form-check-input" type="checkbox" id="fill-last-row" title="Round the selected count up to fill the last row when enough videos remain."><span class="form-check-label small">Fill last row</span></label><span id="page-size-status" class="small text-body-secondary"></span>
            </div>
            <div class="d-flex align-items-center justify-content-center gap-2"><button class="btn btn-sm btn-outline-secondary" id="previous-page" type="button">Previous</button><label class="small" for="page-number">Page</label><input class="form-control form-control-sm" style="width: 6rem" id="page-number" type="number" min="1" step="1"><span id="page-status" class="small text-body-secondary"></span><button class="btn btn-sm btn-outline-secondary" id="next-page" type="button">Next</button></div>
            <label class="visually-hidden" for="page-slider">Choose library page</label><input class="form-range" id="page-slider" type="range" min="1" max="1" step="1">
        </nav>
    </div>
</section>
