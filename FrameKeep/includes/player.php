<section class="card mb-4" aria-labelledby="player-heading">
    <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h2 class="h6 mb-0" id="player-heading">Now playing</h2>
        <div class="d-flex gap-2"><button class="btn btn-sm btn-outline-secondary" id="browse-playing" type="button" disabled>Location</button><button class="btn btn-sm btn-outline-secondary" id="edit-playing" type="button" disabled>Edit video</button></div>
    </div>
    <div class="player-screen bg-black"><video id="video-player" controls preload="metadata" playsinline aria-label="Video player"></video></div>
    <div class="card-body">
        <div id="playing-tags" class="d-flex flex-wrap gap-2 mb-3" aria-label="Tags for the current video"></div>
        <div id="playing-rating" class="mb-2"></div><p id="playing-media" class="small text-body-secondary mb-2"></p>
        <p class="fw-semibold mb-2" id="playing-title">Select a video from your library.</p>
        <p class="small text-body-secondary mb-3" id="queue-status">Nothing queued.</p>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button class="btn btn-outline-secondary" id="previous-video" type="button" aria-label="Previous video" disabled><i class="bi bi-skip-start-fill" aria-hidden="true"></i></button>
            <button class="btn btn-info" id="play-pause" type="button" aria-label="Play or pause" disabled><i class="bi bi-play-fill" aria-hidden="true"></i></button>
            <button class="btn btn-outline-secondary" id="next-video" type="button" aria-label="Next video" disabled><i class="bi bi-skip-end-fill" aria-hidden="true"></i></button>
            <button class="btn btn-outline-secondary" id="shuffle" type="button" aria-label="Shuffle" aria-pressed="false"><i class="bi bi-shuffle" aria-hidden="true"></i></button>
            <label class="small ms-2" for="repeat-mode">Repeat</label>
            <select class="form-select w-auto" id="repeat-mode"><option value="off">Off</option><option value="video">Video</option><option value="queue">Playlist / sequence</option></select>
        </div>
        <details class="mt-3"><summary class="small text-body-secondary">Playback queue</summary><div id="playback-queue" class="list-group mt-2 queue-list"></div></details>
        <label class="form-check mt-3"><input class="form-check-input" type="checkbox" id="autoplay-open"><span class="form-check-label small">Autoplay on open</span></label>
    </div>
</section>
