<section class="card mb-4" aria-labelledby="player-heading">
    <div class="card-header player-heading-bar">
        <h2 class="h6 mb-0 text-center" id="player-heading"><i class="bi bi-play-circle text-info me-2" aria-hidden="true"></i>FrameKeep <small class="text-body-secondary">v<?= htmlspecialchars(APP_VERSION, ENT_QUOTES, 'UTF-8') ?></small></h2>
    </div>
    <div class="player-screen bg-black"><video id="video-player" controls preload="metadata" playsinline aria-label="Video player"></video></div>
    <div class="card-body">
        <div id="playing-tags" class="d-flex flex-wrap gap-2 mb-3" aria-label="Tags for the current video"></div>
        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
            <p class="fw-semibold mb-0" id="playing-title">Select a video from your library.</p>
            <div id="playing-rating"></div>
            <p id="playing-media" class="small text-body-secondary mb-0"></p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button class="btn btn-outline-secondary" id="previous-video" type="button" aria-label="Previous video" disabled><i class="bi bi-skip-start-fill" aria-hidden="true"></i></button>
            <button class="btn btn-info" id="play-pause" type="button" aria-label="Play or pause" disabled><i class="bi bi-play-fill" aria-hidden="true"></i></button>
            <button class="btn btn-outline-secondary" id="next-video" type="button" aria-label="Next video" disabled><i class="bi bi-skip-end-fill" aria-hidden="true"></i></button>
            <button class="btn btn-outline-secondary" id="shuffle" type="button" aria-label="Shuffle" aria-pressed="false"><i class="bi bi-shuffle" aria-hidden="true"></i></button>
            <label class="small ms-2" for="repeat-mode">Repeat</label>
            <select class="form-select w-auto" id="repeat-mode"><option value="off">Off</option><option value="video">Video</option><option value="queue">Playlist / sequence</option></select>
            <div class="d-flex flex-wrap gap-2 border-start ps-3 ms-1"><button class="btn btn-outline-secondary" id="browse-playing" type="button" disabled>Location</button><button class="btn btn-outline-secondary" id="edit-playing" type="button" disabled>Edit video</button><button class="btn btn-outline-secondary" id="playlist-playing" type="button" disabled>Add to playlist</button></div>
        </div>
        <details class="mt-3 border rounded p-2"><summary class="fw-semibold text-body">Playback queue <span class="ms-2 badge text-bg-secondary" id="queue-status">Empty</span><button class="btn btn-sm btn-outline-secondary ms-2" id="clear-queue" type="button">Clear queue</button></summary><div id="playback-queue" class="list-group mt-2 queue-list"></div></details>
    </div>
    <div class="card-footer d-flex flex-wrap align-items-center justify-content-end gap-2">
        <label class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2 me-auto mb-0"><input class="form-check-input m-0" type="checkbox" id="autoplay-open"><span>Autoplay on open</span></label>
        <button class="btn btn-sm btn-outline-secondary" type="button" id="open-missing" title="Missing files" data-bs-toggle="modal" data-bs-target="#missing-list-dialog">Missing files</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" id="open-attention" title="Attention" data-bs-toggle="modal" data-bs-target="#attention-list-dialog">Attention</button>
        <button class="btn btn-sm btn-outline-secondary" id="open-log" type="button" aria-label="View error log" title="View error log"><i class="bi bi-journal-text" aria-hidden="true"></i></button>
        <button class="btn btn-sm btn-outline-secondary" id="open-help" type="button" aria-label="Help" title="How to use FrameKeep"><i class="bi bi-question-circle" aria-hidden="true"></i></button>
        <button class="btn btn-sm btn-outline-secondary" id="open-settings" type="button" aria-label="Settings" title="Settings"><i class="bi bi-gear" aria-hidden="true"></i></button>
    </div>
</section>
