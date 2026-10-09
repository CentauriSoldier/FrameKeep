(() => {
    'use strict';

    const $ = (id) => document.getElementById(id);
    const root = $('framekeep');
    if (!root) return;

    const player = $('video-player');
    let state = { videos: [], tags: [], playlists: [] };
    let videoMap = new Map();
    let currentPlaylist = 0;
    let tagModes = new Map();
    const untaggedUnlocked = new Set();
    let tagObservers = [];
    let selected = new Set();
    let page = 1;
    let restoredPage = null;
    try {
        const savedPage = Number(localStorage.getItem('framekeep-page'));
        if (Number.isInteger(savedPage) && savedPage > 0) restoredPage = savedPage;
    } catch (_) {}
    const downloads = [];
    let downloading = false;
    let downloadCount = 0;
    let downloadFailures = 0;
    let bulkThumbnailsRunning = false;
    let stopBulkThumbnails = false;
    let gridColumns = 0;
    let pendingPagination = null;
    let paginationSequence = 0;
    let queue = [];
    let unshuffledQueue = null;
    let draggedQueueIndex = null;
    let queueIndex = -1;
    let standaloneVideo = null;
    function currentVideoId() { return standaloneVideo || queue[queueIndex]; }
    let editingVideo = null;
    let bulkTagVideos = [];
    let managerKind = 'tag';
    let activityLoading = false;
    let lastThumbnailPoll = 0;
    let thumbnailJobs = 0;
    let scanWasActive = false;
    let scanning = false;
    let scanTimer = null;
    let scanSchedule = null;
    let requestChain = Promise.resolve();
    let resumePosition = null;
    let progressSaving = false;
    let lastCheckpoint = '';
    let audioTimer = null;
    let savedAudio = '1:false';
    const playbackSession = Array.from(crypto.getRandomValues(new Uint32Array(4)), (value) => value.toString(16)).join('-');
    let saveSequence = 0;
    let playbackReady = false;

    function playbackRequest(data) {
        return { ...data, session: playbackSession, sequence: ++saveSequence };
    }
    let settings = {};
    try { settings = JSON.parse(localStorage.getItem('framekeep-settings') || '{}'); } catch (_) {}
    let sequence = Array.isArray(settings.sequence) ? settings.sequence.map(Number) : [];
    let shuffle = Boolean(settings.shuffle);
    unshuffledQueue = Array.isArray(settings.unshuffledQueue) ? settings.unshuffledQueue : null;
    $('repeat-mode').value = ['off', 'video', 'queue'].includes(settings.repeat) ? settings.repeat : 'off';

    function saveSettings() {
        try {
            localStorage.setItem('framekeep-settings', JSON.stringify({
                sequence, shuffle, unshuffledQueue, repeat: $('repeat-mode').value
            }));
        } catch (_) {}
    }

    function make(tag, className = '', text = '') {
        const element = document.createElement(tag);
        element.className = className;
        element.textContent = text;
        return element;
    }

    function button(text, handler, className = 'btn btn-sm btn-outline-secondary') {
        const element = make('button', className, text);
        element.type = 'button';
        element.addEventListener('click', handler);
        return element;
    }

    function durationLabel(value) {
        if (value === null || value === undefined) return 'Duration unknown';
        const seconds = Math.floor(Number(value));
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor(seconds % 3600 / 60);
        return (hours ? hours + ':' + String(minutes).padStart(2, '0') : minutes) + ':' + String(seconds % 60).padStart(2, '0');
    }
    function fileSizeLabel(value) {
        if (value === null || value === undefined) return 'Size unknown';
        const bytes = Number(value);
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const unit = bytes > 0 ? Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1000))) : 0;
        return (bytes / Math.pow(1000, unit)).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' ' + units[unit];
    }
    function mediaLabel(video) {
        return durationLabel(video.duration) + ' · ' + (video.video_width && video.video_height ? video.video_width + ' × ' + video.video_height : 'Resolution unknown');
    }
    function ratingControl(video, showLabel = true) {
        const group = make('div', 'd-flex flex-wrap gap-1');
        group.setAttribute('aria-label', 'Video rating');
        for (let rating = 1; rating <= 5; rating++) {
            const current = Number(video.rating || 0);
            const star = button('', () => mutate('video_rating', { video: video.id, rating: current === rating ? 0 : rating }), 'btn btn-sm btn-link text-decoration-none p-1 ' + (rating <= current ? 'text-white' : 'text-body-secondary'));
            const icon = make('i', rating <= current ? 'bi bi-star-fill' : 'bi bi-star');
            icon.setAttribute('aria-hidden', 'true');
            star.append(icon);
            star.title = current === rating ? 'Clear rating' : 'Rate ' + rating + ' stars';
            star.setAttribute('aria-label', star.title);
            star.setAttribute('aria-pressed', String(rating === current));
            group.append(star);
        }
        return group;
    }
    let mediaInspecting = false;
    let mediaRetryAt = 0;
    const mediaAttempts = new Set();
    async function enrichMedia() {
        if (mediaInspecting || Date.now() < mediaRetryAt || !state.settings || document.hidden) return;
        const candidates = state.videos.filter(video => !Number(video.media_checked) && !Number(video.missing) && !libraryOffline(video) && !mediaAttempts.has(video.id));
        const ids = candidates.slice(0, 3).map(video => video.id);
        if (!ids.length) return;
        mediaInspecting = true;
        ids.forEach(id => mediaAttempts.add(id));
        try {
            const response = await fetch(root.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'media_inspect', videos: ids }) });
            const payload = await response.json();
            if (!response.ok || payload.error) throw new Error(payload.error || 'Media inspection failed.');
            const returned = new Set(payload.media.map(metadata => metadata.id));
            for (const id of ids) if (!returned.has(id)) mediaAttempts.delete(id);
            if (returned.size < ids.length) mediaRetryAt = Date.now() + 30000;
            for (const metadata of payload.media) {
                const video = videoMap.get(metadata.id);
                if (video) Object.assign(video, metadata);
            }
            renderLibrary();
            renderPlayer();
            renderAttention();
        } catch (error) {
            mediaRetryAt = Date.now() + 30000;
            ids.forEach(id => mediaAttempts.delete(id));
            showError(error);
        } finally { mediaInspecting = false; }
    }
    setInterval(enrichMedia, 2000);

    function styleTag(element, tag) {
        element.style.color = tag.text_color;
        element.style.backgroundColor = tag.background_color;
        element.style.fontFamily = tag.font;
        element.style.fontSize = Number(tag.font_size) + 'px';
        return element;
    }

    function showError(error, dialog = null) {
        reportError(error.message || String(error));
        const target = dialog ? $(dialog).querySelector('.dialog-error') : $('app-message');
        target.textContent = error.message || String(error);
        target.classList.remove('d-none');
    }

    function clearError(dialog = null) {
        const target = dialog ? $(dialog).querySelector('.dialog-error') : $('app-message');
        target.textContent = '';
        if (!dialog) target.classList.add('d-none');
    }

    const logUrl = new URL('log_api.php', new URL(root.dataset.thumbnail, document.baseURI)).href;
    function reportError(message, detail = '') {
        fetch(logUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'append', message, detail }), keepalive: true }).catch(() => {});
    }
    window.addEventListener('error', (event) => { if (event.message) reportError(event.message, (event.filename || '') + ':' + event.lineno); });
    window.addEventListener('unhandledrejection', (event) => reportError(String(event.reason && event.reason.message || event.reason)));

    let displayedLog = [];
    function logText(entry) {
        return entry.time + ' [' + entry.source + '] ' + entry.message + '\n' + JSON.stringify(entry.context || {}, null, 2);
    }
    async function copyLog(text) {
        try {
            await navigator.clipboard.writeText(text);
            $('log-copy-status').textContent = 'Copied to clipboard.';
        } catch (_) {
            const field = $('log-copy-text');
            field.value = text;
            field.hidden = false;
            field.focus();
            field.select();
            $('log-copy-status').textContent = 'Automatic copying is unavailable. Copy the selected text below using your keyboard or context menu.';
        }
    }
    async function renderLog() {
        clearError('log-dialog');
        $('log-events').replaceChildren(make('p', 'text-body-secondary', 'Loading log…'));
        try {
            const response = await fetch(logUrl, { cache: 'no-store' });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Could not read log.');
            displayedLog = result.entries;
            $('log-events').replaceChildren();
            for (const entry of result.entries) {
                const card = make('div', 'log-event border rounded p-3');
                const date = new Date(entry.time);
                const time = make('div', 'log-time small fw-semibold', Number.isNaN(date.getTime()) ? entry.time : date.toLocaleString());
                const detail = make('div', 'log-detail');
                detail.append(make('div', 'small text-info mb-1', entry.source), make('div', '', entry.message));
                detail.append(button('Copy', () => copyLog(logText(entry))));
                if (entry.context && Object.keys(entry.context).length) detail.append(make('pre', 'small mb-0 mt-2 text-body-secondary', JSON.stringify(entry.context, null, 2)));
                card.append(time, detail);
                $('log-events').append(card);
            }
            if (!result.entries.length) $('log-events').append(make('p', 'text-body-secondary', 'No logged errors.'));
        } catch (error) { showError(error, 'log-dialog'); }
    }
    $('open-log').addEventListener('click', () => { modal('log-dialog').show(); renderLog(); });
    $('refresh-log').addEventListener('click', renderLog);
    $('copy-all-log').addEventListener('click', () => copyLog(displayedLog.map(logText).join('\n\n')));
    $('clear-log').addEventListener('click', async () => {
        if (!confirm('Empty the error log? This cannot be undone.')) return;
        try {
            const response = await fetch(logUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'clear' }) });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Could not clear log.');
            await renderLog();
        } catch (error) { showError(error, 'log-dialog'); }
    });

    function modal(id) {
        return bootstrap.Modal.getOrCreateInstance($(id));
    }

    function applyState(next) {
        const playingId = currentVideoId();
        state = next;
        root.dataset.thumbnailSize = state.settings.thumbnail_size;
        $('autoplay-open').checked = Boolean(Number(state.playback.autoplay));
        const schedule = Number(state.settings.auto_scan) ? Number(state.settings.scan_frequency) : 0;
        if (schedule !== scanSchedule) {
            clearInterval(scanTimer);
            scanTimer = schedule > 0 ? setInterval(scanLibrary, schedule * 1000) : null;
            scanSchedule = schedule;
        }
        state.tags = state.tags.map((tag) => ({ ...tag, id: Number(tag.id) }));
        videoMap = new Map(state.videos.map((video) => [video.id, video]));
        for (const video of state.videos) if (!Number(video.media_checked)) mediaAttempts.delete(video.id);
        if (queue.some((id) => !videoMap.has(id))) {
            queue = queue.filter((id) => videoMap.has(id));
            queueIndex = queue.indexOf(playingId);
        }
        const validTags = new Set(state.tags.map((tag) => tag.id));
        tagModes = new Map([...tagModes].filter(([id]) => validTags.has(id)));
        selected = new Set([...selected].filter((id) => videoMap.has(id)));
        if (!state.playlists.some((playlist) => playlist.id === currentPlaylist)) currentPlaylist = 0;
        sequence = sequence.filter((id) => state.playlists.some((playlist) => playlist.id === id));
        saveSettings();
        render();
    }

    function api(action, data = {}) {
        const task = requestChain.then(async () => {
            const response = await fetch(root.dataset.api, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...data, action })
            });
            let payload;
            try { payload = await response.json(); } catch (_) {
                throw new Error('The server returned an unreadable response. Check PHP and SQLite are enabled.');
            }
            if (!response.ok || payload.error) throw new Error(payload.error || 'The library request failed.');
            applyState(payload.state);
            return payload;
        });
        requestChain = task.catch(() => {});
        return task;
    }

    async function mutate(action, data, dialog = null) {
        clearError(dialog);
        try {
            await api(action, data);
            return true;
        } catch (error) {
            showError(error, dialog);
            return false;
        }
    }

    async function scanLibrary() {
        if (scanning) return;
        scanning = true;
        $('rescan').disabled = true;
        $('scan-status').textContent = 'Scanning library…';
        refreshActivity(true);
        try {
            const payload = await api('scan');
            if (payload.scan.status === 'busy') {
                $('scan-status').textContent = 'Another library scan is running.';
            } else if (payload.scan.errors.length) {
                $('scan-status').textContent = 'Scan incomplete: ' + payload.scan.errors.length + ' unreadable folder(s).';
                showError(new Error('Some folders could not be read. Existing library records were kept. First folder: ' + payload.scan.errors[0]));
            } else {
                clearError();
                $('scan-status').textContent = 'Library ready.';
            }
        } catch (error) {
            $('scan-status').textContent = 'Scan failed.';
            showError(error);
        } finally {
            scanning = false;
            $('rescan').disabled = false;
            refreshActivity(true);
        }
    }

    async function refreshActivity(force = false) {
        if (activityLoading || (!force && document.hidden)) return;
        if (!force && !scanning && !scanWasActive && !thumbnailJobs && Date.now() - lastThumbnailPoll < 5000) return;
        activityLoading = true;
        lastThumbnailPoll = Date.now();
        try {
            const response = await fetch(root.dataset.progress, { cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            const active = Boolean(data.scan && data.scan.running);
            $('scan-activity').classList.toggle('d-none', !active);
            if (active) {
                const labels = { discovering: 'Discovering videos', matching: 'Checking library records', importing: 'Importing videos' };
                const progress = data.scan.total === null ? data.scan.discovered + ' videos found' : data.scan.processed + ' / ' + data.scan.total;
                const current = data.scan.current || '';
                $('scan-activity').textContent = (labels[data.scan.phase] || 'Scanning') + ' · ' + progress + (current ? ' · ' + (current.length > 70 ? '…' + current.slice(-69) : current) : '');
                $('scan-activity').title = current;
            } else {
                $('scan-activity').textContent = '';
                $('scan-activity').removeAttribute('title');
            }
            const thumbs = data.thumbnails;
            if (thumbs.counted) $('thumbnail-count').textContent = 'Thumbnails: ' + thumbs.ready + ' ready · ' + thumbs.pending + ' pending' + (thumbs.unchecked ? ' · ' + thumbs.unchecked + ' awaiting scan' : '') + ' · ' + thumbs.cached + ' cached images';
            $('thumbnail-activity').classList.toggle('d-none', !thumbs.generating);
            $('thumbnail-activity').textContent = thumbs.generating ? 'Creating thumbnails · ' + thumbs.generating + ' active' + (thumbs.current ? ' · ' + thumbs.current : '') : '';
            if (active) $('scan-status').textContent = 'Scanning library…';
            else if (thumbs.generating) $('scan-status').textContent = 'Creating thumbnails…';
            else if (scanWasActive || thumbnailJobs) $('scan-status').textContent = 'Library ready.';
            scanWasActive = active;
            thumbnailJobs = thumbs.generating;
        } catch (_) {
            // A status refresh must not interrupt playback or library actions.
        } finally { activityLoading = false; }
    }
    setInterval(refreshActivity, 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshActivity(true); });

    function libraryOffline(video) {
        return video && ['offline', 'disabled'].includes(video.library_status);
    }

    function visibleVideos() {
        const playlist = state.playlists.find((item) => item.id === currentPlaylist);
        let videos = playlist ? playlist.items.map((item) => videoMap.get(item.video_id)).filter(Boolean) : [...state.videos];
        const search = $('video-search').value.trim().toLocaleLowerCase();
        videos = videos.filter((video) => {
            const needsAttention = attentionReasons(video).length > 0;
            if ($('attention-filter').value === 'only' ? !needsAttention : needsAttention) return false;
            const rating = $('rating-filter').value;
            if (rating !== 'all' && (rating === '0' ? Number(video.rating) !== 0 : Number(video.rating) < Number(rating))) return false;
            const duration = $('duration-filter').value;
            if (duration !== 'all') {
                if (duration === 'unknown' ? video.duration !== null && video.duration !== undefined : video.duration === null || video.duration === undefined) return false;
                if (duration === 'short' && Number(video.duration) >= 600) return false;
                if (duration === 'medium' && (Number(video.duration) < 600 || Number(video.duration) >= 3600)) return false;
                if (duration === 'long' && Number(video.duration) < 3600) return false;
            }
            const resolution = $('resolution-filter').value;
            if (resolution === 'unknown' && video.video_height) return false;
            if (resolution !== 'all' && resolution !== 'unknown' && Number(video.video_height || 0) < Number(resolution)) return false;
            if (search && !video.name.toLocaleLowerCase().includes(search)) return false;
            if ($('untagged-only').checked && video.tags.length) return false;
            for (const [tagId, mode] of tagModes) {
                if (mode === 'include' && !video.tags.includes(tagId)) return false;
                if (mode === 'exclude' && video.tags.includes(tagId)) return false;
            }
            return true;
        });
        const order = $('video-sort').value;
        if (order === 'rating') videos.sort((a, b) => Number(b.rating) - Number(a.rating) || a.name.localeCompare(b.name));
        else if (order === 'duration') videos.sort((a, b) => (a.duration ?? Infinity) - (b.duration ?? Infinity) || a.name.localeCompare(b.name));
        else if (order === 'resolution') videos.sort((a, b) => Number(b.video_width || 0) * Number(b.video_height || 0) - Number(a.video_width || 0) * Number(a.video_height || 0) || a.name.localeCompare(b.name));
        else if (order !== 'default' || !playlist) {
            videos.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base', numeric: true }) * (order === 'desc' ? -1 : 1));
        }
        return videos;
    }

    function renderSidebar() {
        $('all-videos').classList.add('playlist-card');
        $('all-videos').replaceChildren(make('i', 'bi bi-collection-play playlist-cover-icon'), make('span', 'playlist-title', 'All videos'));
        $('all-videos').classList.toggle('active', !currentPlaylist);
        $('playlist-list').replaceChildren();
        for (const playlist of state.playlists) {
            const entry = make('div', 'btn btn-outline-secondary text-start');
            const selectPlaylist = () => {
                currentPlaylist = playlist.id;
                page = 1;
                selected.clear();
                render();
            };
            entry.tabIndex = 0;
            entry.setAttribute('role', 'button');
            entry.setAttribute('aria-label', playlist.name);
            entry.addEventListener('click', selectPlaylist);
            entry.addEventListener('keydown', (event) => {
                if (event.target !== entry || !['Enter', ' '].includes(event.key)) return;
                event.preventDefault();
                selectPlaylist();
            });
            entry.classList.toggle('active', playlist.id === currentPlaylist);
            entry.classList.add('playlist-card');
            styleTag(entry, playlist);
            entry.replaceChildren();
            const candidates = playlist.items.map((item) => videoMap.get(item.video_id)).filter((video) => video && !Number(video.missing) && !libraryOffline(video));
            const cover = candidates.find((video) => video.id === playlist.cover_video && video.thumbnail_ready) || candidates.find((video) => video.thumbnail_ready);
            if (cover && !Number(cover.missing) && !libraryOffline(cover)) {
                const image = make('img', 'playlist-cover');
                image.alt = '';
                image.loading = 'lazy';
                image.src = root.dataset.thumbnail + '?id=' + encodeURIComponent(cover.id) + '&v=' + state.settings.thumbnail_version;
                entry.append(image);
            } else entry.append(make('i', 'bi bi-music-note-list playlist-cover-icon'));
            entry.append(make('span', 'playlist-title', playlist.name + ' (' + playlist.items.length + ')'));
            const play = button('Play', (event) => {
                event.stopPropagation();
                startQueue(playlist.items.map((item) => item.video_id));
            }, 'btn btn-sm btn-outline-info');
            play.disabled = !candidates.length;
            const enqueue = button('Queue', (event) => {
                event.stopPropagation();
                appendQueue(playlist.items.map((item) => item.video_id));
            }, 'btn btn-sm btn-outline-success');
            enqueue.disabled = !candidates.length;
            const actions = make('div', 'playlist-actions d-flex justify-content-center gap-1');
            actions.append(play, enqueue);
            entry.append(actions);
            $('playlist-list').append(entry);
        }
        if (!state.playlists.length) $('playlist-list').append(make('p', 'small text-body-secondary mb-0', 'No playlists yet.'));
        const untaggedRadio = $('untagged-only');
        const untaggedSelected = untaggedRadio.checked;
        $('tag-filters').replaceChildren();
        tagObservers.forEach((observer) => observer.disconnect());
        tagObservers = [];
        for (const tag of state.tags) {
            const slider = make('div', 'tag-filter-slider');
            slider.tabIndex = 0;
            slider.setAttribute('role', 'slider');
            slider.setAttribute('aria-label', tag.name + ' filter');
            slider.setAttribute('aria-valuemin', '-1');
            slider.setAttribute('aria-valuemax', '1');
            const label = styleTag(make('span', 'tag-filter-name', tag.name), tag);
            const handle = make('span', 'tag-filter-handle');
            handle.setAttribute('aria-hidden', 'true');
            slider.append(label, handle);
            let value = tagModes.get(tag.id) === 'exclude' ? -1 : tagModes.get(tag.id) === 'include' ? 1 : 0;
            function updateSlider(next, apply = true) {
                value = Math.max(-1, Math.min(1, next));
                const mode = value === -1 ? 'exclude' : value === 1 ? 'include' : 'ignore';
                slider.dataset.mode = mode;
                slider.setAttribute('aria-valuenow', String(value));
                slider.setAttribute('aria-valuetext', mode);
                slider.title = tag.name + ': ' + mode + '. Left excludes, center ignores, right includes.';
                if (!apply) return;
                if (mode === 'ignore') tagModes.delete(tag.id); else tagModes.set(tag.id, mode);
                for (const radio of $('tag-filters').querySelectorAll('input[type="radio"]')) radio.checked = false;
                if (mode === 'include') $('untagged-only').checked = false;
                page = 1;
                renderLibrary();
            }
            function pointerValue(event) {
                const bounds = slider.getBoundingClientRect();
                return Math.max(-1, Math.min(1, Math.floor((event.clientX - bounds.left) / bounds.width * 3) - 1));
            }
            let pointer = null;
            slider.addEventListener('pointerdown', (event) => {
                if (event.button !== 0) return;
                pointer = event.pointerId;
                slider.setPointerCapture(pointer);
                slider.focus();
                updateSlider(pointerValue(event));
                event.preventDefault();
            });
            slider.addEventListener('pointermove', (event) => {
                if (pointer === event.pointerId) updateSlider(pointerValue(event));
            });
            const stopDragging = () => { pointer = null; };
            slider.addEventListener('pointerup', stopDragging);
            slider.addEventListener('pointercancel', stopDragging);
            slider.addEventListener('lostpointercapture', stopDragging);
            slider.addEventListener('keydown', (event) => {
                const next = event.key === 'ArrowLeft' || event.key === 'ArrowDown' ? value - 1 : event.key === 'ArrowRight' || event.key === 'ArrowUp' ? value + 1 : event.key === 'Home' ? -1 : event.key === 'End' ? 1 : null;
                if (next === null) return;
                event.preventDefault();
                updateSlider(next);
            });
            updateSlider(value, false);
            const row = make('div', 'tag-filter-row d-flex align-items-stretch gap-1');
            slider.classList.add('flex-grow-1');
            const solo = make('input', 'form-check-input tag-filter-solo m-0');
            solo.type = 'radio';
            solo.name = 'solo-tag';
            solo.checked = tagModes.get(tag.id) === 'include' && state.tags.every((other) => other.id === tag.id || tagModes.get(other.id) === 'exclude');
            solo.setAttribute('aria-label', 'Solo ' + tag.name);
            solo.addEventListener('change', () => {
                for (const other of state.tags) tagModes.set(other.id, other.id === tag.id ? 'include' : 'exclude');
                $('untagged-only').checked = false;
                page = 1;
                render();
            });
            solo.title = 'Show videos with only ' + tag.name + ', excluding every other tag.';
            row.append(solo, slider);
            $('tag-filters').append(row);
        }
        const untaggedRow = make('label', 'd-flex align-items-center gap-1');
        untaggedRadio.checked = untaggedSelected;
        untaggedRadio.className = 'form-check-input tag-filter-solo m-0';
        const untaggedLabel = styleTag(make('span', 'tag-filter-name border rounded flex-grow-1', 'Untagged'), untaggedStyle());
        untaggedRow.append(untaggedRadio, untaggedLabel);
        $('tag-filters').append(untaggedRow);
        const measure = document.createElement('canvas').getContext('2d');
        let longest = 0;
        for (const label of $('tag-filters').querySelectorAll('.tag-filter-name')) {
            measure.font = getComputedStyle(label).font;
            longest = Math.max(longest, measure.measureText(label.textContent).width);
        }
        $('tag-filters').style.setProperty('--tag-control-width', Math.ceil((longest + 12) * 2 + 22) + 'px');
        const playlistMeasure = document.createElement('canvas').getContext('2d');
        let playlistWidth = 0;
        const entries = [$('all-videos'), ...$('playlist-list').querySelectorAll('.playlist-card')];
        for (const entry of entries) {
            playlistMeasure.font = getComputedStyle(entry).font;
            playlistWidth = Math.max(playlistWidth, playlistMeasure.measureText(entry.querySelector('.playlist-title').textContent).width + 24, 120);
        }
        for (const entry of entries) { entry.style.width = Math.ceil(playlistWidth) + 'px'; entry.style.maxWidth = '100%'; }
    }

    function renderLibrary() {
        const videos = visibleVideos();
        const columns = Math.max(1, getComputedStyle($('video-grid')).gridTemplateColumns.split(' ').filter(Boolean).length);
        gridColumns = columns;
        const requestedSize = Number(pendingPagination?.page_size ?? state.settings?.page_size ?? 50);
        const fillLastRow = Boolean(Number(pendingPagination?.fill_last_row ?? state.settings?.fill_last_row ?? 0));
        const pageSize = fillLastRow ? Math.ceil(requestedSize / columns) * columns : requestedSize;
        $('videos-per-page').value = requestedSize;
        $('fill-last-row').checked = fillLastRow;
        $('page-size-status').textContent = pageSize + ' per page';
        const pageCount = Math.max(1, Math.ceil(videos.length / pageSize));
        if (state.settings && restoredPage !== null) {
            page = restoredPage;
            restoredPage = null;
        }
        page = Math.max(1, Math.min(page, pageCount));
        if (state.settings) {
            try { localStorage.setItem('framekeep-page', String(page)); } catch (_) {}
        }
        const playlist = state.playlists.find((item) => item.id === currentPlaylist);
        $('library-heading').textContent = playlist ? playlist.name : 'Video library';
        $('library-count').textContent = videos.length + ' matching · ' + state.videos.length + ' total · ' + selected.size + ' selected';
        $('page-status').textContent = 'of ' + pageCount;
        $('page-number').value = page;
        $('page-number').max = pageCount;
        $('page-slider').max = pageCount;
        $('page-slider').value = page;
        $('page-slider').disabled = pageCount <= 1;
        $('previous-page').disabled = page <= 1;
        $('next-page').disabled = page >= pageCount;
        $('first-page').disabled = page <= 1;
        $('last-page').disabled = page >= pageCount;
        $('play-results').disabled = !videos.length;
        $('select-results').disabled = !videos.length;
        $('batch-playlist').disabled = !selected.size || !state.playlists.length;
        $('delete-selected').disabled = !selected.size;
        for (const format of ['video', 'mp3', 'ogg']) $('download-selected-' + format).disabled = !selected.size;
        $('batch-tags').disabled = !selected.size || !state.tags.length;
        $('video-grid').replaceChildren();
        const currentId = currentVideoId();
        for (const video of videos.slice((page - 1) * pageSize, page * pageSize)) {
            const column = make('div', 'library-column');
            const card = make('div', 'card h-100 video-card');
            const preview = make('img', 'video-preview');
            preview.alt = '';
            preview.loading = 'lazy';
            if (!libraryOffline(video)) preview.src = root.dataset.thumbnail + '?id=' + encodeURIComponent(video.id) + '&v=' + state.settings.thumbnail_version;
            const playAndReveal = () => {
                playVideo(video.id);
                if (Number(state.settings.scroll_to_player)) $('video-player').closest('section').scrollIntoView({
                    block: 'start',
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
                });
            };
            const previewButton = button('', playAndReveal, 'video-preview-button');
            previewButton.setAttribute('aria-label', 'Play ' + video.name);
            previewButton.append(preview);
            previewButton.disabled = libraryOffline(video) || Boolean(Number(video.missing));
            if (libraryOffline(video)) {
                const badge = make('span', 'library-offline-badge badge text-bg-secondary', 'Library ' + (video.library_status === 'disabled' ? 'disabled' : 'offline'));
                badge.prepend(make('i', 'bi bi-plug me-1'));
                previewButton.append(badge);
            }
            card.append(previewButton);
            card.classList.toggle('is-playing', video.id === currentId);
            const body = make('div', 'card-body d-flex flex-column');
            const top = make('div', 'd-flex justify-content-between align-items-center mb-2');
            const icon = make('i', 'bi bi-film fs-3 text-info');
            icon.setAttribute('aria-hidden', 'true');
            const checkbox = make('input', 'form-check-input');
            checkbox.type = 'checkbox';
            checkbox.checked = selected.has(video.id);
            checkbox.setAttribute('aria-label', 'Select ' + video.name);
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) selected.add(video.id); else selected.delete(video.id);
                renderLibrary();
            });
            top.append(icon, checkbox);
            const title = make('h3', 'h6 mb-2');
            title.title = video.name;
            const titleButton = button(video.name, playAndReveal, 'btn btn-link play-video p-0 fw-semibold');
            titleButton.disabled = libraryOffline(video) || Boolean(Number(video.missing));
            title.append(titleButton);
            title.append(ratingControl(video, false), make('div', 'small text-body-secondary mt-1', mediaLabel(video) + ' · ' + fileSizeLabel(video.file_size)));
            const badges = make('div', 'd-flex flex-wrap gap-1 mb-3');
            const tags = state.tags.filter((tag) => video.tags.includes(tag.id));
            if (!tags.length) badges.append(make('span', 'badge text-bg-secondary', 'Untagged'));
            for (const tag of tags) badges.append(styleTag(make('span', 'badge', tag.name), tag));
            const actions = make('div', 'd-flex flex-wrap gap-1 mt-auto');
            const play = button('Play', () => playVideo(video.id), 'btn btn-sm btn-outline-info');
            play.disabled = libraryOffline(video) || Boolean(Number(video.missing));
            actions.append(play);
            const enqueue = button('Queue', () => appendQueue([video.id]), 'btn btn-sm btn-outline-success');
            enqueue.disabled = play.disabled;
            actions.append(enqueue);
            actions.append(button('Edit', () => editVideo(video.id)));
            actions.append(button('Tag', () => openAssignments(video.id, 'tags')));
            actions.append(button('Details', () => showDetails(video.id)));
            actions.append(button('Path', () => browseLocation({ video: video.id })));
            const refresh = button('', () => refreshFileDetails(video, refresh), 'btn btn-sm ' + (attentionReasons(video).length ? 'btn-outline-warning' : 'btn-outline-secondary'));
            refresh.append(make('i', 'bi bi-wrench'));
            refresh.title = 'Refresh file details';
            refresh.setAttribute('aria-label', 'Refresh file details for ' + video.name);
            refresh.disabled = libraryOffline(video) || Boolean(Number(video.missing));
            actions.append(refresh);
            actions.append(button('Delete', () => deleteVideos([video.id]), 'btn btn-sm btn-outline-danger'));
            const downloadActions = make('div', 'd-flex flex-wrap gap-1 border-start ps-2');
            for (const format of ['video', 'mp3', 'ogg']) {
                const download = button(format === 'video' ? '' : ' ' + format.toUpperCase(), () => enqueueDownloads([video.id], format));
                download.prepend(make('i', 'bi bi-download'));
                download.title = 'Download ' + (format === 'video' ? 'video' : format.toUpperCase() + ' audio');
                download.setAttribute('aria-label', download.title + ' for ' + video.name);
                download.disabled = play.disabled;
                downloadActions.append(download);
            }
            actions.append(downloadActions);
            if (playlist) {
                actions.append(button('Remove', async () => {
                    await mutate('playlist_remove', { playlist: playlist.id, video: video.id });
                }));
                const itemIndex = playlist.items.findIndex((item) => item.video_id === video.id);
                const up = button('↑', () => reorderPlaylist(playlist, itemIndex, -1));
                const down = button('↓', () => reorderPlaylist(playlist, itemIndex, 1));
                up.setAttribute('aria-label', 'Move ' + video.name + ' up');
                down.setAttribute('aria-label', 'Move ' + video.name + ' down');
                up.disabled = itemIndex <= 0;
                down.disabled = itemIndex >= playlist.items.length - 1;
                actions.append(up, down);
            }
            body.append(top, title, badges, actions);
            card.append(body);
            column.append(card);
            $('video-grid').append(column);
        }
        if (!videos.length) {
            $('video-grid').append(make('p', 'text-center text-body-secondary py-5 mb-0', state.videos.length ? 'No videos match these filters.' : 'No videos in the library yet.'));
        }
    }

    async function reorderPlaylist(playlist, index, offset) {
        const items = playlist.items.map((item) => item.id);
        [items[index], items[index + offset]] = [items[index + offset], items[index]];
        await mutate('playlist_order', { playlist: playlist.id, items });
    }

    function randomize(ids) {
        const result = [...ids];
        for (let index = result.length - 1; index > 0; index--) {
            const other = Math.floor(Math.random() * (index + 1));
            [result[index], result[other]] = [result[other], result[index]];
        }
        return result;
    }

    function playVideo(id) {
        standaloneVideo = id;
        queueIndex = -1;
        playCurrent();
    }

    function appendQueue(ids) {
        const added = ids.filter((id) => videoMap.has(id) && !libraryOffline(videoMap.get(id)) && !Number(videoMap.get(id).missing));
        queue.push(...added);
        if (unshuffledQueue) unshuffledQueue.push(...added);
        renderPlayer();
        saveQueue();
    }

    function startQueue(ids, startId = null) {
        standaloneVideo = null;
        ids = ids.filter((id) => videoMap.has(id) && !libraryOffline(videoMap.get(id)) && !Number(videoMap.get(id).missing));
        if (!ids.length) return;
        unshuffledQueue = shuffle ? [...ids] : null;
        queue = shuffle ? randomize(ids) : [...ids];
        if (shuffle && startId) {
            const index = queue.indexOf(startId);
            if (index >= 0) [queue[0], queue[index]] = [queue[index], queue[0]];
        }
        queueIndex = startId && !shuffle ? Math.max(0, queue.indexOf(startId)) : 0;
        saveQueue();
        playCurrent();
    }

    async function saveQueue() {
        saveSettings();
        try {
            const response = await fetch(root.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(playbackRequest({ action: 'playback_queue', queue: [...queue], queue_index: queueIndex })), keepalive: true });
            await checkPlaybackSave(response, 'Playback queue could not be saved.');
        } catch (error) { showError(error); }
    }

    async function playCurrent(autoplay = true, position = 0) {
        if (libraryOffline(videoMap.get(currentVideoId()))) {
            renderPlayer();
            $('queue-status').textContent = 'Library offline or disabled. Your saved playback is preserved.';
            return;
        }
        const video = videoMap.get(currentVideoId());
        if (!video) return;
        clearError();
        const url = new URL(root.dataset.stream, document.baseURI);
        url.searchParams.set('id', video.id);
        resumePosition = position;
        player.src = url.href;
        player.load();
        renderPlayer();
        renderLibrary();
        if (!autoplay) return;
        try { await player.play(); } catch (error) {
            if (error.name !== 'NotAllowedError' && error.name !== 'AbortError') reportPlaybackError(error);
        }
    }

    function reportPlaybackError(exception = null) {
        if (!player.getAttribute('src')) return;
        const error = player.error;
        const reasons = {
            1: 'The browser interrupted the video request.',
            2: 'The browser could not read the video from the server.',
            3: 'The browser received the video but could not decode it.',
            4: 'The browser rejected the video source or format.'
        };
        let message = error ? (reasons[error.code] || 'Video playback failed.') + ' Media error code: ' + error.code + '.' : 'Video playback failed.';
        if (error && error.message) message += ' Browser detail: ' + error.message;
        if (exception) message += ' Playback request: ' + exception.name + ': ' + exception.message;
        showError(new Error(message));
    }

    function progressData() {
        const video = currentVideoId();
        if (!playbackReady || !video || !videoMap.has(video) || resumePosition !== null || !player.getAttribute('src') || player.readyState < 1) return null;
        return playbackRequest({ action: 'playback_save', video, position: player.currentTime, queue, queue_index: queueIndex, volume: player.volume, muted: player.muted });
    }

    async function checkPlaybackSave(response, description) {
        let payload;
        try { payload = await response.json(); } catch (_) {
            throw new Error(description + ' The server returned an unreadable response (HTTP ' + response.status + ').');
        }
        if (!response.ok || payload.error) {
            throw new Error(description + ' ' + (payload.error || 'The server rejected the request (HTTP ' + response.status + ').'));
        }
        if (payload.stale) throw new Error(description + ' This save was superseded by a newer request or browser session.');
        if (payload.saved !== true) throw new Error(description + ' The server did not confirm that the change was saved.');
    }

    async function saveProgress() {
        const data = progressData();
        if (!data || progressSaving) return;
        const checkpoint = data.video + ':' + data.position;
        if (checkpoint === lastCheckpoint) return;
        progressSaving = true;
        try {
            const response = await fetch(root.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            await checkPlaybackSave(response, 'Playback position could not be saved.');
            lastCheckpoint = checkpoint;
        } catch (error) { showError(error); }
        finally { progressSaving = false; }
    }

    player.addEventListener('loadedmetadata', () => {
        if (resumePosition !== null) {
            player.currentTime = Math.min(resumePosition, Number.isFinite(player.duration) ? Math.max(0, player.duration - 0.1) : resumePosition);
            resumePosition = null;
        }
        saveProgress();
    });
    player.addEventListener('pause', saveProgress);
    player.addEventListener('seeked', saveProgress);
    player.addEventListener('volumechange', () => {
        const signature = player.volume + ':' + player.muted;
        if (!playbackReady || signature === savedAudio) return;
        clearTimeout(audioTimer);
        audioTimer = setTimeout(async () => {
            const data = playbackRequest({ action: 'playback_audio', volume: player.volume, muted: player.muted });
            try {
                const response = await fetch(root.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                await checkPlaybackSave(response, 'Volume could not be saved.');
                savedAudio = data.volume + ':' + data.muted;
            } catch (error) { showError(error); }
        }, 250);
    });
    setInterval(saveProgress, 10000);
    window.addEventListener('pagehide', () => {
        const data = progressData();
        if (data) navigator.sendBeacon(root.dataset.api, new Blob([JSON.stringify(data)], { type: 'application/json' }));
        if (playbackReady) navigator.sendBeacon(root.dataset.api, new Blob([JSON.stringify(playbackRequest({ action: 'playback_audio', volume: player.volume, muted: player.muted }))], { type: 'application/json' }));
    });
    $('autoplay-open').addEventListener('change', async () => {
        const wanted = $('autoplay-open').checked;
        try {
            const response = await fetch(root.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(playbackRequest({ action: 'playback_autoplay', autoplay: wanted })) });
            await checkPlaybackSave(response, 'Autoplay preference could not be saved.');
        } catch (error) { $('autoplay-open').checked = !wanted; showError(error); }
    });

    function advance(offset, ended = false) {
        if (!queue.length) return;
        if (ended && $('repeat-mode').value === 'video') {
            player.currentTime = 0;
            player.play().catch((error) => showError(error));
            return;
        }
        let next = queueIndex;
        for (let attempts = 0; attempts < queue.length; attempts++) {
            next += offset;
            if (next >= queue.length || next < 0) {
                if ($('repeat-mode').value === 'queue') next = (next + queue.length) % queue.length;
                else return;
            }
            const candidate = videoMap.get(queue[next]);
            if (!candidate || libraryOffline(candidate) || Number(candidate.missing)) continue;
            standaloneVideo = null;
            queueIndex = next;
            playCurrent();
            return;
        }
    }

    function renderPlayer() {
        const video = videoMap.get(currentVideoId());
        $('playing-tags').replaceChildren();
        if (!state.tags.length) $('playing-tags').append(make('span', 'small text-body-secondary', 'Create tags in the sidebar to assign them here.'));
        for (const tag of state.tags) {
            const assigned = Boolean(video && video.tags.includes(tag.id));
            const label = make('label', 'playing-tag d-flex align-items-center gap-2 border rounded px-3 py-2');
            const control = make('input', 'form-check-input m-0');
            control.type = 'checkbox';
            control.checked = assigned;
            control.disabled = !video;
            control.addEventListener('change', async () => {
                const wanted = control.checked;
                if (wanted) clearTags.checked = false;
                control.disabled = true;
                const saved = await mutate('video_tag', { video: video.id, tag: tag.id, assigned: wanted });
                if (!saved) { control.checked = assigned; renderPlayer(); }
                control.disabled = false;
            });
            label.classList.toggle('border-info', assigned);
            styleTag(label, tag);
            label.append(control, make('span', '', tag.name));
            $('playing-tags').append(label);
        }
        const untagged = styleTag(make('label', 'playing-tag d-flex align-items-center gap-2 border rounded'), untaggedStyle());
        const clearTags = make('input', 'form-check-input m-0');
        clearTags.type = 'checkbox';
        clearTags.disabled = !video;
        clearTags.checked = Boolean(video && !video.tags.length && !untaggedUnlocked.has(video.id));
        clearTags.addEventListener('change', async () => {
            if (!video) return;
            if (!clearTags.checked) { untaggedUnlocked.add(video.id); renderPlayer(); return; }
            clearTags.disabled = true;
            if (await mutate('video_tags_save', { video: video.id, choices: [] })) untaggedUnlocked.delete(video.id);
            renderPlayer();
        });
        untagged.append(clearTags, make('span', '', 'Untagged'));
        $('playing-tags').append(untagged);
        $('playing-rating').replaceChildren(...(video ? [ratingControl(video)] : []));
        $('playing-media').textContent = video ? mediaLabel(video) + ' · ' + fileSizeLabel(video.file_size) : '';
        $('playing-title').textContent = video ? video.name : 'Select a video from your library.';
        $('queue-status').textContent = libraryOffline(video) ? video.library_name + ': library ' + video.library_status + '. Records and saved playback are preserved.' : queue.length ? (standaloneVideo ? 'Not in queue · ' : (queueIndex + 1) + ' of ') + queue.length + (standaloneVideo ? ' queued' : '') : 'Empty';
        $('edit-playing').disabled = !video;
        $('browse-playing').disabled = !video;
        $('playlist-playing').disabled = !video;
        for (const format of ['video', 'mp3', 'ogg']) $('download-playing-' + format).disabled = !video || libraryOffline(video) || Boolean(Number(video.missing));
        const refresh = $('refresh-playing');
        refresh.disabled = !video || libraryOffline(video) || Boolean(Number(video.missing));
        const flagged = Boolean(video && attentionReasons(video).length);
        refresh.classList.toggle('btn-outline-warning', flagged);
        refresh.classList.toggle('btn-outline-secondary', !flagged);
        $('play-pause').disabled = !video || libraryOffline(video) || Boolean(Number(video.missing));
        $('previous-video').disabled = !video || !queue.length || Boolean(standaloneVideo) || (queueIndex === 0 && $('repeat-mode').value !== 'queue');
        $('next-video').disabled = !video || !queue.length || (queueIndex === queue.length - 1 && $('repeat-mode').value !== 'queue');
        $('shuffle').classList.toggle('active', shuffle);
        $('shuffle').setAttribute('aria-pressed', String(shuffle));
        $('playback-queue').replaceChildren();
        $('clear-queue').disabled = !queue.length;
        queue.forEach((id, index) => {
            const video = videoMap.get(id);
            if (!video) return;
            const entry = make('div', 'list-group-item list-group-item-action');
            const playEntry = () => {
                standaloneVideo = null;
                queueIndex = index;
                playCurrent();
            };
            const play = button('', playEntry, 'btn text-start text-reset d-flex align-items-center gap-2 flex-grow-1 p-0');
            play.setAttribute('aria-label', 'Play ' + video.name);
            const remove = button('×', () => {
                if (!standaloneVideo && queueIndex === index) { standaloneVideo = id; queueIndex = -1; }
                else if (queueIndex > index) queueIndex--;
                queue.splice(index, 1);
                if (unshuffledQueue) {
                    const originalIndex = unshuffledQueue.indexOf(id);
                    if (originalIndex >= 0) unshuffledQueue.splice(originalIndex, 1);
                }
                renderPlayer();
                saveQueue();
            }, 'btn btn-sm btn-outline-secondary flex-shrink-0');
            remove.setAttribute('aria-label', 'Remove ' + video.name + ' from queue');
            entry.draggable = true;
            entry.addEventListener('dragstart', (event) => {
                draggedQueueIndex = index;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(index));
            });
            entry.addEventListener('dragend', () => { draggedQueueIndex = null; });
            entry.addEventListener('dragover', (event) => { if (draggedQueueIndex !== null) event.preventDefault(); });
            entry.addEventListener('drop', (event) => {
                event.preventDefault();
                const from = draggedQueueIndex;
                draggedQueueIndex = null;
                if (from === null || from === index) return;
                if (!standaloneVideo && queueIndex >= 0) {
                    if (queueIndex === from) queueIndex = index;
                    else if (from < queueIndex && index >= queueIndex) queueIndex--;
                    else if (from > queueIndex && index <= queueIndex) queueIndex++;
                }
                const moved = queue.splice(from, 1)[0];
                queue.splice(index, 0, moved);
                shuffle = false;
                unshuffledQueue = null;
                renderPlayer();
                saveQueue();
            });
            entry.classList.toggle('active', index === queueIndex);
            entry.classList.add('d-flex', 'align-items-center', 'gap-2');
            const label = make('span', '', (index + 1) + '. ' + video.name);
            const preview = make('span', 'd-flex align-items-center justify-content-center flex-shrink-0');
            preview.style.width = '3rem';
            preview.style.height = '2rem';
            if (video.thumbnail_ready) {
                const image = make('img', 'rounded');
                image.alt = '';
                image.loading = 'lazy';
                image.style.width = '100%';
                image.style.height = '100%';
                image.style.objectFit = 'contain';
                image.src = root.dataset.thumbnail + '?id=' + encodeURIComponent(id) + '&v=' + state.settings.thumbnail_version;
                image.addEventListener('error', () => preview.replaceChildren(make('i', 'bi bi-film')));
                preview.append(image);
            } else preview.append(make('i', 'bi bi-film'));
            play.append(preview, label);
            entry.replaceChildren(remove, play);
            $('playback-queue').append(entry);
        });
    }

    function untaggedStyle() {
        return { text_color: '#ffffff', background_color: '#6c757d', font: 'system-ui', font_size: 12, ...state.untagged };
    }

    function checkboxes(container, records, chosen) {
        container.replaceChildren();
        const tagList = container.id === 'video-tags' || (container.id === 'assignment-choices' && assignmentKind === 'tags');
        if (!records.length && !tagList) {
            container.append(make('span', 'small text-body-secondary', 'None created yet.'));
            return;
        }
        for (const record of records) {
            const label = make('label', 'form-check');
            const input = make('input', 'form-check-input');
            input.type = 'checkbox';
            input.value = record.id;
            input.checked = chosen.includes(record.id);
            label.append(input, make('span', 'form-check-label', record.name));
            container.append(label);
        }
        if (tagList) {
            const label = make('label', 'form-check');
            const input = make('input', 'form-check-input');
            input.type = 'checkbox'; input.value = 'untagged'; input.checked = !chosen.length;
            label.append(input, make('span', 'form-check-label', 'Untagged'));
            const update = () => {
                for (const other of container.querySelectorAll('input')) {
                    if (other === input) continue;
                    if (input.checked) other.checked = false;
                }
            };
            input.addEventListener('change', update);
            for (const other of container.querySelectorAll('input')) {
                other.addEventListener('change', () => { if (other.checked) input.checked = false; });
            }
            update();
            container.append(label);
        }
    }

    let assignmentVideo = null;
    let assignmentKind = 'tags';

    function openAssignments(id, kind) {
        const video = videoMap.get(id);
        if (!video) return;
        assignmentVideo = id;
        assignmentKind = kind;
        clearError('assignment-dialog');
        $('assignment-title').textContent = kind === 'tags' ? 'Tag video' : 'Add to playlist';
        $('assignment-video-name').textContent = video.name;
        $('assignment-note').textContent = kind === 'tags' ? 'Select the tags for this video. Unchecked tags will be removed when you save.' : 'Select playlists to add this video to. Existing memberships are kept.';
        checkboxes($('assignment-choices'), kind === 'tags' ? state.tags : state.playlists, kind === 'tags' ? video.tags : state.playlists.filter((playlist) => playlist.items.some((item) => item.video_id === id)).map((playlist) => playlist.id));
        modal('assignment-dialog').show();
    }

    function editVideo(id) {
        const video = videoMap.get(id);
        if (!video) return;
        editingVideo = id;
        clearError('video-dialog');
        $('video-name').value = video.name;
        $('video-location').textContent = video.path;
        prepareThumbnailPreview(video);
        checkboxes($('video-tags'), state.tags, video.tags);
        checkboxes($('video-playlists'), state.playlists, state.playlists.filter((playlist) => playlist.items.some((item) => item.video_id === id)).map((playlist) => playlist.id));
        modal('video-dialog').show();
    }


    function prepareThumbnailPreview(video) {
        const preview = $('video-thumbnail-preview');
        const slider = $('video-thumbnail-time');
        const selectedTime = Number(video.thumbnail_custom_time ?? state.settings.thumbnail_time);
        $('video-thumbnail-current').textContent = 'Current time: ' + selectedTime + ' s (' + (video.thumbnail_custom_time == null ? 'global default' : 'custom') + ')';
        $('video-thumbnail-position').textContent = selectedTime + ' s';
        $('video-thumbnail-status').textContent = 'Select a frame, then Set thumbnail. This saves immediately, independently of the other Edit fields.';
        slider.disabled = true;
        $('set-video-thumbnail').disabled = true;
        preview.pause();
        preview.onloadedmetadata = () => {
            if (editingVideo !== video.id || !Number.isFinite(preview.duration) || preview.duration <= 0) return;
            slider.max = Math.max(0, Math.ceil(preview.duration) - 1);
            slider.value = Math.min(selectedTime, Number(slider.max));
            slider.disabled = false;
            $('set-video-thumbnail').disabled = false;
            preview.currentTime = Number(slider.value);
            $('video-thumbnail-position').textContent = slider.value + ' s';
        };
        preview.onerror = () => { $('video-thumbnail-status').textContent = 'This browser cannot preview this video format. No thumbnail changes were saved.'; };
        if (libraryOffline(video) || Number(video.missing)) {
            preview.removeAttribute('src'); preview.load();
            $('video-thumbnail-status').textContent = 'The video file is unavailable.';
            return;
        }
        const url = new URL(root.dataset.stream, document.baseURI);
        url.searchParams.set('id', video.id);
        preview.src = url.href;
        preview.load();
    }
    $('video-thumbnail-time').addEventListener('input', () => {
        const time = Number($('video-thumbnail-time').value);
        $('video-thumbnail-position').textContent = time + ' s';
        $('video-thumbnail-preview').currentTime = time;
    });
    $('video-dialog').addEventListener('hidden.bs.modal', () => {
        const preview = $('video-thumbnail-preview');
        preview.onloadedmetadata = null; preview.onerror = null;
        preview.pause(); preview.removeAttribute('src'); preview.load();
    });
    $('set-video-thumbnail').addEventListener('click', async () => {
        const id = editingVideo;
        const time = Number($('video-thumbnail-time').value);
        $('set-video-thumbnail').disabled = true;
        $('video-thumbnail-status').textContent = 'Saving time and generating thumbnail…';
        try {
            const payload = await api('video_thumbnail_time', { video: id, time });
            applyState(payload.state);
            const response = await fetch(root.dataset.thumbnail + '?id=' + encodeURIComponent(id) + '&regenerate=1&v=' + state.settings.thumbnail_version, { cache: 'no-store' });
            const blob = await response.blob();
            if (!response.ok || !response.headers.get('Content-Type')?.startsWith('image/jpeg') || !blob.size) throw new Error('The custom time was saved, but thumbnail generation failed. See the log for details.');
            if (editingVideo === id) {
                $('video-thumbnail-current').textContent = 'Current time: ' + time + ' s (custom)';
                $('video-thumbnail-status').textContent = 'Thumbnail saved. Rescans and cache clearing preserve this time.';
            }
            render();
            refreshActivity(true);
        } catch (error) {
            showError(error, 'video-dialog');
            $('video-thumbnail-status').textContent = error.message;
        } finally { if (editingVideo === id) $('set-video-thumbnail').disabled = false; }
    });

    function chosenValues(id) {
        return [...$(id).querySelectorAll('input:checked')].filter((input) => input.value !== 'untagged').map((input) => Number(input.value));
    }

    async function showDetails(id) {
        clearError('details-dialog');
        $('file-details').replaceChildren(make('dd', 'col-12', 'Loading file details…'));
        modal('details-dialog').show();
        try {
            const { details } = await api('video_details', { video: id });
            $('file-details').replaceChildren();
            const date = (value) => value === null ? 'Not available from this filesystem' : new Date(Number(value) * 1000).toLocaleString();
            const bytes = details.file_size === null ? null : Number(details.file_size);
            const units = ['bytes', 'KiB', 'MiB', 'GiB', 'TiB'];
            const unit = bytes === null || bytes === 0 ? 0 : Math.min(4, Math.floor(Math.log(bytes) / Math.log(1024)));
            const size = bytes === null ? 'Unknown' : (bytes / Math.pow(1024, unit)).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' ' + units[unit] + ' (' + bytes.toLocaleString() + ' bytes)' + (details.size_cached ? ' — last recorded' : '');
            const fields = [
                ['Display name', details.name], ['Filename', details.filename], ['Path', details.path],
                ['Rating', Number(details.rating || 0) + ' / 5'], ['Duration', durationLabel(details.duration)], ['Resolution', details.video_width && details.video_height ? details.video_width + ' × ' + details.video_height : 'Unknown'],
                ['File size', size], ['Created', date(details.created)], ['Modified', date(details.modified)],
                ['Tags', details.tags.join(', ') || 'Untagged'], ['Playlists', details.playlists.join(', ') || 'None'],
                ['File status', details.available ? 'Available' : 'Missing or inaccessible']
            ];
            if (details.metadata_changed !== null) fields.push(['Metadata changed', date(details.metadata_changed)]);
            for (const [label, value] of fields) {
                $('file-details').append(make('dt', 'col-sm-3', label), make('dd', 'col-sm-9 file-path', value));
            }
        } catch (error) {
            $('file-details').replaceChildren();
            showError(error, 'details-dialog');
        }
    }

    function renderManager() {
        $('manager-title').textContent = managerKind === 'tag' ? 'Manage tags' : 'Manage playlists';
        $('manager-list').replaceChildren();
        const records = managerKind === 'tag' ? [...state.tags, { id: 'untagged', name: 'Untagged', ...untaggedStyle() }] : state.playlists;
        for (const record of records) {
            const row = make('div', 'd-flex flex-wrap gap-2 border rounded p-2');
            const name = make('input', 'form-control');
            name.value = record.name;
            name.disabled = record.id === 'untagged';
            name.setAttribute('aria-label', 'Rename ' + record.name);
            const namePreview = make('div', 'manager-name-preview w-100');
            const previewSpace = make('div', 'd-flex justify-content-center align-items-center');
            namePreview.append(name, previewSpace);
            row.append(namePreview);
            const appearance = {};
            {
                const preview = styleTag(make('span', 'badge align-self-center', record.name), record);
                previewSpace.append(preview);
                for (const [key, caption] of [['text_color', 'Text color'], ['background_color', 'Background color']]) {
                    const picker = make('input', 'form-control form-control-color');
                    picker.type = 'color';
                    picker.value = record[key];
                    picker.title = caption;
                    picker.setAttribute('aria-label', caption + ' for ' + record.name);
                    appearance[key] = picker;
                    row.append(picker);
                }
                const font = make('select', 'form-select form-select-sm w-auto');
                for (const value of ['system-ui', 'Arial', 'Verdana', 'Georgia', 'serif', 'monospace']) font.add(new Option(value === 'system-ui' ? 'System font' : value, value));
                font.value = record.font;
                font.setAttribute('aria-label', 'Font for ' + record.name);
                appearance.font = font;
                const size = make('input', 'form-control form-control-sm tag-font-size');
                size.type = 'number'; size.min = 10; size.max = 24; size.value = record.font_size;
                size.title = 'Font size in pixels';
                size.setAttribute('aria-label', 'Font size for ' + record.name);
                appearance.font_size = size;
                row.append(font, size);
                const updatePreview = () => styleTag(preview, Object.fromEntries(Object.entries(appearance).map(([key, input]) => [key, input.value])));
                Object.values(appearance).forEach((input) => input.addEventListener('input', updatePreview));
                name.addEventListener('input', () => { preview.textContent = name.value; });
            }
            if (managerKind === 'playlist') {
                const cover = make('select', 'form-select form-select-sm w-100');
                cover.setAttribute('aria-label', 'Playlist cover for ' + record.name);
                cover.add(new Option('Playlist cover: neutral icon', ''));
                for (const item of record.items) {
                    const video = videoMap.get(item.video_id);
                    if (video) cover.add(new Option(video.name, video.id));
                }
                cover.value = record.cover_video || '';
                appearance.cover_video = cover;
                row.append(cover);
            }
            row.append(button('Save', async () => {
                const style = Object.fromEntries(Object.entries(appearance).map(([key, input]) => [key, input.value]));
                await mutate(managerKind + '_save', { id: record.id, name: name.value, ...style }, 'manager-dialog');
            }));
            if (record.id !== 'untagged') row.append(button('Delete', async () => {
                const detail = managerKind === 'tag' ? 'It will be removed from every video.' : 'Videos will stay in your library.';
                if (confirm('Delete "' + record.name + '"? ' + detail)) {
                    if (await mutate(managerKind + '_delete', { id: record.id }, 'manager-dialog')) renderManager();
                }
            }, 'btn btn-sm btn-outline-danger'));
            $('manager-list').append(row);
        }
        if (!records.length) $('manager-list').append(make('p', 'small text-body-secondary', 'None created yet.'));
    }

    function openManager(kind) {
        managerKind = kind;
        $('manager-name').value = '';
        clearError('manager-dialog');
        renderManager();
        modal('manager-dialog').show();
    }

    function playlistOptions(id) {
        $(id).replaceChildren();
        for (const playlist of state.playlists) $(id).add(new Option(playlist.name, playlist.id));
    }

    function renderSequence() {
        $('sequence-list').replaceChildren();
        sequence.forEach((id, index) => {
            const playlist = state.playlists.find((item) => item.id === id);
            if (!playlist) return;
            const row = make('div', 'd-flex align-items-center gap-2');
            row.append(make('span', 'flex-grow-1', (index + 1) + '. ' + playlist.name));
            const move = (offset) => {
                [sequence[index], sequence[index + offset]] = [sequence[index + offset], sequence[index]];
                saveSettings();
                renderSequence();
            };
            const up = button('↑', () => move(-1));
            const down = button('↓', () => move(1));
            up.disabled = index === 0;
            down.disabled = index === sequence.length - 1;
            up.setAttribute('aria-label', 'Move playlist up');
            down.setAttribute('aria-label', 'Move playlist down');
            row.append(up, down, button('Remove', () => {
                sequence.splice(index, 1);
                saveSettings();
                renderSequence();
            }));
            $('sequence-list').append(row);
        });
        if (!sequence.length) $('sequence-list').append(make('p', 'small text-body-secondary', 'Add playlists in the order you want to hear them.'));
        $('sequence-play').disabled = !sequence.length;
        $('sequence-add').disabled = !state.playlists.length;
    }

    function render() {
        renderSidebar();
        renderLibrary();
        renderPlayer();
        renderMissing();
        renderAttention();
    }

    function attentionReasons(video) {
        if (Number(video.missing) || libraryOffline(video)) return [];
        const reasons = [];
        if (video.file_size !== null && video.file_size !== undefined && Number(video.file_size) === 0) reasons.push('Empty file');
        if (Number(video.media_checked)) {
            if (video.duration === null || video.duration === undefined) reasons.push('Duration unavailable');
            if (!(Number(video.video_width) > 0 && Number(video.video_height) > 0)) reasons.push('Resolution unavailable');
        }
        return reasons;
    }

    async function refreshFileDetails(video, control) {
        control.disabled = true;
        try {
            const payload = await api('video_repair', { video: video.id });
            applyState(payload.state);
            alert(payload.repair.message + '\nThe result was recorded in the log.');
        } catch (error) {
            showError(error);
            alert(error.message + '\nSee the log for details.');
        } finally { control.disabled = false; }
    }

    $('refresh-playing').addEventListener('click', () => {
        const video = videoMap.get(currentVideoId());
        if (video) refreshFileDetails(video, $('refresh-playing'));
    });

    function renderAttention() {
        const needsAttention = state.videos.some((video) => attentionReasons(video).length > 0);
        $('open-attention').classList.toggle('btn-outline-warning', needsAttention);
        $('open-attention').classList.toggle('btn-outline-secondary', !needsAttention);
        $('attention-files').replaceChildren();
        for (const video of state.videos) {
            const reasons = attentionReasons(video);
            if (!reasons.length) continue;
            const card = make('div', 'border rounded p-2 d-grid gap-2 text-center');
            card.append(make('div', 'small fw-semibold text-break', video.filename || video.name), make('div', 'small text-warning border rounded p-2', reasons.join(' · ')));
            const info = button('', () => {
                $('attention-name').textContent = video.name;
                $('attention-reasons').textContent = reasons.join(' · ');
                $('attention-path').value = video.path;
                $('attention-copy-status').textContent = '';
                modal('attention-list-dialog').hide();
                modal('attention-dialog').show();
            });
            info.setAttribute('aria-label', 'File information: ' + video.name);
            info.append(make('i', 'bi bi-question-circle'));
            info.title = 'Why this file needs attention';
            const repair = button('', async () => {
                repair.disabled = true;
                try {
                    const payload = await api('video_repair', { video: video.id });
                    applyState(payload.state);
                    alert(payload.repair.message + '\nThe result was recorded in the log.');
                } catch (error) {
                    showError(error);
                    alert(error.message + '\nSee the log for details.');
                } finally { repair.disabled = false; }
            });
            repair.append(make('i', 'bi bi-wrench'));
            repair.title = 'Try to auto-repair';
            repair.setAttribute('aria-label', 'Try to auto-repair ' + video.name);
            const actions = make('div', 'd-flex justify-content-center gap-2');
            actions.append(info, repair);
            card.append(actions);
            $('attention-files').append(card);
        }
        if (!$('attention-files').children.length) $('attention-files').append(make('p', 'small text-body-secondary mb-0', 'No flagged files. Files awaiting inspection are not flagged for unknown details.'));
    }

    $('copy-attention-path').addEventListener('click', async () => {
        const field = $('attention-path');
        try {
            await navigator.clipboard.writeText(field.value);
            $('attention-copy-status').textContent = 'Path copied.';
        } catch (_) {
            field.focus();
            field.select();
            $('attention-copy-status').textContent = 'Copy the selected path using your keyboard or context menu.';
        }
    });

    function renderMissing() {
        $('missing-files').replaceChildren();
        for (const library of (state.libraries || []).filter((location) => location.status !== 'online')) {
            $('missing-files').append(make('p', 'small text-body-secondary mb-2', library.name + ': library ' + library.status + '. Records are preserved.'));
        }
        const missing = state.videos.filter((video) => Number(video.missing) && video.library_status === 'online');
        $('open-missing').classList.toggle('btn-outline-warning', missing.length > 0);
        $('open-missing').classList.toggle('btn-outline-secondary', missing.length === 0);
        if (!missing.length) $('missing-files').append(make('span', 'small text-body-secondary', 'No missing files.'));
        for (const video of missing) {
            const item = make('div', 'small');
            item.append(make('strong', '', video.name), make('p', 'file-path text-body-secondary mb-2', video.path));
            for (const candidate of video.candidates) {
                const renamed = candidate.filename !== video.filename;
                item.append(make('p', 'mb-1 text-info', renamed ? 'Possible rename: same file size. Is this the new file?' : 'Matching filename:'), make('p', 'file-path mb-1', candidate.path), button('Use this file', () => mutate('video_relink', { video: video.id, path: candidate.path }), 'btn btn-sm btn-outline-info mb-2'));
            }
            if (!video.candidates.length) item.append(make('p', 'mb-0', 'No matching filename or file size found.'));
            item.append(button('Remove library record', async () => {
                if (!confirm('Remove "' + video.name + '" from the library? Its tag assignments, edited name, and playlist entries will be removed. No disk files or global tags will be deleted.')) return;
                await mutate('missing_remove', { video: video.id, confirmed: true });
            }, 'btn btn-sm btn-outline-warning mt-2 mb-3'));
            $('missing-files').append(item);
        }
    }

    async function deleteVideos(ids) {
        const videos = ids.map((id) => videoMap.get(id)).filter(Boolean);
        if (!videos.length) return;
        const message = 'Permanently delete ' + videos.length + ' video file(s) from disk and the library?\n\nThis also removes their tags and playlist entries. This cannot be undone.\n\n' + videos.slice(0, 10).map((video) => video.name).join('\n') + (videos.length > 10 ? '\n…and ' + (videos.length - 10) + ' more.' : '');
        if (!window.confirm(message)) return;
        clearError();
        if (videos.some((video) => video.id === currentVideoId())) {
            player.pause();
            player.removeAttribute('src');
            player.load();
        }
        try {
            const payload = await api('video_delete', { videos: videos.map((video) => video.id), confirmed: true });
            if (payload.deletion.errors.length) showError(new Error(payload.deletion.errors.join('\n')));
            $('scan-status').textContent = payload.deletion.deleted + ' video file(s) deleted.';
        } catch (error) { showError(error); }
    }

    $('delete-selected').addEventListener('click', () => deleteVideos([...selected]));
    for (const format of ['video', 'mp3', 'ogg']) {
        $('download-selected-' + format).addEventListener('click', () => enqueueDownloads([...selected], format));
        $('download-playing-' + format).addEventListener('click', () => enqueueDownloads([currentVideoId()], format));
    }

    function enqueueDownloads(ids, format) {
        if (!downloading) { downloadCount = 0; downloadFailures = 0; }
        for (const id of ids) {
            const video = videoMap.get(id);
            if (video && !libraryOffline(video) && !Number(video.missing)) downloads.push({ id, name: video.name, format });
        }
        processDownloads();
    }

    async function processDownloads() {
        if (downloading || !downloads.length) return;
        downloading = true;
        const status = $('download-status');
        status.hidden = false;
        let links = $('download-links');
        if (!links) {
            links = make('div', 'd-flex flex-wrap gap-2 mb-3');
            links.id = 'download-links';
            status.after(links);
        }
        while (downloads.length) {
            const job = downloads.shift();
            status.textContent = 'Preparing ' + job.name + ' (' + job.format.toUpperCase() + ') · ' + downloads.length + ' queued. Keep this page open; your browser may ask to allow multiple downloads.';
            $('scan-status').textContent = status.textContent;
            try {
                const response = await fetch('php/download.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: job.id, format: job.format }) });
                const body = await response.text();
                let result;
                try { result = JSON.parse(body); }
                catch (_) { throw new Error('Download preparation returned HTTP ' + response.status + ' instead of a download response. Check the log; the server may have stopped the conversion.'); }
                if (!response.ok || result.error) throw new Error(result.error || 'Download preparation failed (HTTP ' + response.status + ').');
                if (!result.token) throw new Error('The server did not return a download token.');
                const link = make('a', 'btn btn-sm btn-outline-info', 'Download ' + job.name + ' (' + job.format.toUpperCase() + ')');
                link.href = 'php/download.php?token=' + encodeURIComponent(result.token);
                link.download = '';
                link.title = 'Click if the automatic download did not start.';
                links.append(link);
                const downloadFrame = make('iframe');
                downloadFrame.hidden = true;
                downloadFrame.title = 'File download';
                document.body.append(downloadFrame);
                downloadFrame.src = 'php/download.php?token=' + encodeURIComponent(result.token);
                downloadCount++;
                // Native downloads are handed to the browser without buffering large videos in memory.
                await new Promise((resolve) => setTimeout(resolve, 1500));
            } catch (error) {
                downloadFailures++;
                $('scan-status').textContent = 'Download failed: ' + error.message;
                showError(error);
            }
        }
        downloading = false;
        status.textContent = downloadCount + ' downloads sent to your browser' + (downloadFailures ? ' · ' + downloadFailures + ' failed' : '') + '. Browser transfers may continue; allow multiple downloads if prompted. If nothing starts, use the download links above.';
        $('scan-status').textContent = downloadFailures ? 'Downloads finished with errors. See the library message or Log.' : 'Downloads prepared. If nothing starts, use the download links above the library.';
    }
    $('batch-tags').addEventListener('click', () => {
        bulkTagVideos = [...selected];
        clearError('bulk-tags-dialog');
        $('bulk-tags-count').textContent = bulkTagVideos.length + ' selected video(s)';
        $('bulk-tags-mode').value = 'add';
        checkboxes($('bulk-tags-list'), state.tags, []);
        modal('bulk-tags-dialog').show();
    });
    $('bulk-tags-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const tags = [...$('bulk-tags-list').querySelectorAll('input:checked')].map((input) => Number(input.value));
        if (await mutate('video_tags_bulk', { videos: bulkTagVideos, tags, mode: $('bulk-tags-mode').value }, 'bulk-tags-dialog')) modal('bulk-tags-dialog').hide();
    });

    async function browseLocation(data) {
        try {
            const response = await fetch(root.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'browse', ...data }) });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.error || 'Could not find the file location.');
            if (payload.opened) return;
            $('browse-path').value = payload.path;
            $('browse-status').textContent = 'Copy the path or select it manually.';
            modal('browse-dialog').show();
        } catch (error) { showError(error); }
    }
    $('browse-playing').addEventListener('click', () => browseLocation({ video: currentVideoId() }));
    $('copy-browse-path').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText($('browse-path').value);
            $('browse-status').textContent = 'Path copied.';
        } catch (_) {
            $('browse-path').select();
            $('browse-status').textContent = 'Press Ctrl+C to copy the selected path.';
        }
    });

    $('restore-database').addEventListener('click', async () => {
        const file = $('restore-file').files[0];
        if (!file) { showError(new Error('Choose a database backup first.'), 'settings-dialog'); return; }
        if (!confirm('Replace the current library metadata with this backup? Changes made since that backup will be replaced. Video files and thumbnails will not be changed. A recovery database snapshot will be saved first.')) return;
        $('restore-database').disabled = true;
        clearError('settings-dialog');
        const data = new FormData();
        data.append('backup', file);
        data.append('confirmed', 'true');
        try {
            const response = await fetch(root.dataset.restore, { method: 'POST', body: data });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.error || 'Database restoration failed.');
            playbackReady = false;
            clearInterval(scanTimer);
            clearTimeout(audioTimer);
            player.pause();
            $('restore-status').replaceChildren(make('span', '', 'Backup restored. '));
            const recovery = make('a', '', 'Download the recovery snapshot');
            recovery.href = payload.recovery;
            recovery.download = '';
            $('restore-status').append(recovery, make('span', '', '. '), button('Reload FrameKeep', () => window.location.reload()));
            $('settings-form').querySelector('button[type="submit"]').disabled = true;
        } catch (error) { showError(error, 'settings-dialog'); }
        finally { $('restore-database').disabled = false; }
    });


    document.querySelectorAll('#settings-dialog [data-help]').forEach((element) => {
        new bootstrap.Tooltip(element, { title: element.dataset.help, trigger: 'hover focus', container: $('settings-dialog') });
        if (element.matches('input[type="checkbox"]')) {
            const label = element.closest('label');
            new bootstrap.Tooltip(label, { title: element.dataset.help, trigger: 'hover', container: $('settings-dialog') });
        }
    });

    function renderLocations() {
        $('library-locations').replaceChildren();
        for (const library of state.libraries || []) {
            const row = make('div', 'border rounded p-2');
            const label = make('label', 'form-check');
            const enabled = make('input', 'form-check-input');
            enabled.type = 'checkbox';
            enabled.checked = Boolean(Number(library.enabled));
            enabled.addEventListener('change', async () => {
                if (await mutate('library_enabled', { library: library.id, enabled: enabled.checked }, 'settings-dialog')) renderLocations();
                else enabled.checked = Boolean(Number(library.enabled));
            });
            label.append(enabled, make('span', 'form-check-label', library.name + ' · ' + library.status));
            const remove = button('Remove library — keep files', async () => {
                const count = state.videos.filter((video) => Number(video.library_id) === library.id).length;
                const message = 'Remove "' + library.name + '" and its ' + count + ' video record(s) from FrameKeep?\n\nNo video files will be deleted from disk. This removes their playlist entries, video-specific tag assignments, and edited display names. Global tags, playlists, and settings are preserved.\n\nTo import the videos again, add this folder as a library and rescan.';
                if (!confirm(message)) return;
                const current = videoMap.get(currentVideoId());
                if (await mutate('library_delete', { library: library.id, confirmed: true }, 'settings-dialog')) {
                    if (current && Number(current.library_id) === library.id) {
                        player.pause();
                        player.removeAttribute('src');
                        player.load();
                    }
                    renderLocations();
                    $('scan-status').textContent = 'Library removed. Video files were kept.';
                }
            }, 'btn btn-sm btn-outline-danger mt-2');
            remove.title = 'Remove this location and its database records only. Files on disk, global tags, playlists, and settings are kept.';
            const browse = button('Browse', () => browseLocation({ library: library.id }), 'btn btn-sm btn-outline-secondary mt-2 me-2');
            row.append(label, make('div', 'small text-body-secondary text-break', library.path), browse, remove);
            $('library-locations').append(row);
        }
    }
    let chosenFolder = '';
    let folderParent = null;
    let folderRequest = 0;
    async function loadFolders(path = '') {
        const request = ++folderRequest;
        $('folder-list').replaceChildren(make('span', 'small', 'Loading folders…'));
        $('choose-folder').disabled = true;
        $('folder-up').disabled = true;
        try {
            const response = await fetch(root.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'folder_list', path }) });
            const payload = await response.json();
            if (request !== folderRequest) return;
            if (!response.ok) throw new Error(payload.error || 'Folders could not be listed.');
            chosenFolder = payload.path;
            folderParent = payload.parent;
            $('folder-current').textContent = chosenFolder || 'Choose a location';
            $('folder-up').disabled = !folderParent;
            $('choose-folder').disabled = !chosenFolder;
            $('folder-list').replaceChildren();
            for (const folder of payload.folders) $('folder-list').append(button(folder.name, () => loadFolders(folder.path), 'btn btn-sm btn-outline-secondary text-start text-break'));
            if (!payload.folders.length) $('folder-list').append(make('span', 'small text-body-secondary', chosenFolder ? 'No readable subfolders.' : 'No readable locations found. You can still enter a network path manually.'));
        } catch (error) {
            if (request !== folderRequest) return;
            chosenFolder = '';
            $('folder-list').replaceChildren(make('span', 'small text-danger', error.message));
        }
    }
    $('browse-library-path').addEventListener('click', () => {
        $('folder-picker').classList.remove('d-none');
        loadFolders($('library-path').value.trim());
    });
    $('folder-roots').addEventListener('click', () => loadFolders());
    $('folder-up').addEventListener('click', () => loadFolders(folderParent));
    $('cancel-folder').addEventListener('click', () => { folderRequest++; $('folder-picker').classList.add('d-none'); });
    $('choose-folder').addEventListener('click', () => {
        if (!chosenFolder) return;
        $('library-path').value = chosenFolder;
        $('folder-picker').classList.add('d-none');
    });

    $('add-library').addEventListener('click', async () => {
        if (await mutate('library_add', { name: $('library-name').value, path: $('library-path').value }, 'settings-dialog')) {
            $('library-name').value = '';
            $('library-path').value = '';
            renderLocations();
            $('scan-status').textContent = 'Library added. Use Rescan to import videos, or wait for the next automatic scan.';
        }
    });

    function filterHelp() {
        const search = $('help-search').value.trim().toLocaleLowerCase();
        let matches = 0;
        document.querySelectorAll('#help-dialog [data-help-section]').forEach((section) => {
            const visible = !search || section.textContent.toLocaleLowerCase().includes(search);
            section.classList.toggle('d-none', !visible);
            if (visible) matches++;
        });
        $('help-empty').classList.toggle('d-none', matches > 0);
    }
    $('open-help').addEventListener('click', () => {
        $('help-search').value = '';
        filterHelp();
        $('help-dialog').querySelector('.modal-body').scrollTop = 0;
        modal('help-dialog').show();
    });
    $('help-search').addEventListener('input', filterHelp);
    document.querySelectorAll('#help-dialog [data-help-jump]').forEach((control) => {
        control.addEventListener('click', () => {
            $('help-search').value = '';
            filterHelp();
            $(control.dataset.helpJump).scrollIntoView({ block: 'start' });
        });
    });

    $('open-settings').addEventListener('click', () => {
        clearError('settings-dialog');
        $('settings-save-status').textContent = 'Options save automatically.';
        renderLocations();
        $('scan-on-start').checked = Boolean(state.settings && Number(state.settings.scan_on_start));
        $('auto-orphan').checked = Boolean(Number(state.settings.auto_orphan));
        $('auto-scan').checked = Boolean(Number(state.settings.auto_scan));
        $('scan-frequency').value = state.settings.scan_frequency;
        $('thumbnail-size').value = state.settings.thumbnail_size;
        $('thumbnail-time').value = state.settings.thumbnail_time;
        $('scroll-to-player').checked = Boolean(Number(state.settings.scroll_to_player));
        $('settings-autoplay').checked = Boolean(Number(state.playback.autoplay));
        $('backup-database').href = root.dataset.backup;
        modal('settings-dialog').show();
    });
    let settingsSaveSequence = 0;
    async function saveGlobalSettings() {
        if (!$('scan-frequency').checkValidity() || !$('thumbnail-time').checkValidity()) {
            $('settings-save-status').textContent = 'Enter valid numeric settings to save changes.';
            return;
        }
        const sequence = ++settingsSaveSequence;
        $('settings-save-status').textContent = 'Saving…';
        const saved = await mutate('settings_save', { scan_on_start: $('scan-on-start').checked, auto_orphan: $('auto-orphan').checked, auto_scan: $('auto-scan').checked, scan_frequency: Number($('scan-frequency').value), thumbnail_size: $('thumbnail-size').value, thumbnail_time: Number($('thumbnail-time').value), scroll_to_player: $('scroll-to-player').checked, autoplay: $('settings-autoplay').checked }, 'settings-dialog');
        if (sequence === settingsSaveSequence) $('settings-save-status').textContent = saved ? 'Changes saved.' : 'Changes could not be saved. Change an option to retry.';
    }
    for (const id of ['scan-on-start', 'auto-orphan', 'auto-scan', 'scan-frequency', 'thumbnail-size', 'thumbnail-time', 'scroll-to-player', 'settings-autoplay']) {
        $(id).addEventListener('change', saveGlobalSettings);
    }
    $('settings-form').addEventListener('submit', (event) => {
        event.preventDefault();
        saveGlobalSettings();
    });
    $('clear-thumbnails').addEventListener('click', async () => {
        if (!confirm('Clear all cached thumbnails? They will regenerate when viewed. Custom thumbnail times are preserved. Your video files are untouched.')) return;
        if (await mutate('thumbnail_clear', {}, 'settings-dialog')) $('scan-status').textContent = 'Thumbnail cache cleared.';
    });
    $('reset-thumbnail-times').addEventListener('click', async () => {
        if (!confirm('Reset all thumbnail times? This removes every custom choice and makes all videos use the global default. Images regenerate as needed; video files are untouched.')) return;
        if (await mutate('thumbnail_times_reset', {}, 'settings-dialog')) $('scan-status').textContent = 'Custom thumbnail times reset to the global default.';
    });
    $('generate-thumbnails').addEventListener('click', async () => {
        if (bulkThumbnailsRunning) return;
        const videos = state.videos.filter((video) => !Number(video.missing) && !libraryOffline(video));
        if (!videos.length) {
            $('bulk-thumbnail-status').textContent = 'No available videos to process.';
            return;
        }
        bulkThumbnailsRunning = true;
        stopBulkThumbnails = false;
        $('generate-thumbnails').disabled = true;
        $('clear-thumbnails').disabled = true;
        $('stop-thumbnails').hidden = false;
        let processed = 0;
        let failed = 0;
        try {
            for (const video of videos) {
                if (stopBulkThumbnails) break;
                $('bulk-thumbnail-status').textContent = 'Processing ' + (processed + 1) + ' / ' + videos.length + ' · ' + video.name;
                try {
                    const response = await fetch(root.dataset.thumbnail + '?id=' + encodeURIComponent(video.id) + '&v=' + state.settings.thumbnail_version, { cache: 'no-store' });
                    const image = await response.blob();
                    if (!response.ok || !response.headers.get('Content-Type')?.startsWith('image/jpeg') || !image.size) {
                        failed++;
                        reportError('Thumbnail request did not return an image: ' + video.name, 'HTTP ' + response.status + ' · ' + video.path);
                    }
                } catch (error) { failed++; reportError('Thumbnail request failed: ' + video.name, error.message + ' · ' + video.path); }
                processed++;
                refreshActivity(true);
            }
            $('bulk-thumbnail-status').textContent = (stopBulkThumbnails ? 'Stopped. ' : 'Complete. ') + processed + ' / ' + videos.length + ' processed · ' + failed + ' failed. Cached previews were reused; unsuccessful previews can be retried.';
        } finally {
            bulkThumbnailsRunning = false;
            $('generate-thumbnails').disabled = false;
            $('clear-thumbnails').disabled = false;
            $('stop-thumbnails').hidden = true;
            refreshActivity(true);
        }
    });
    $('stop-thumbnails').addEventListener('click', () => {
        stopBulkThumbnails = true;
        $('bulk-thumbnail-status').textContent = 'Stopping after the current thumbnail finishes…';
    });
    $('rescan').addEventListener('click', scanLibrary);
    $('all-videos').addEventListener('click', () => {
        currentPlaylist = 0;
        page = 1;
        selected.clear();
        render();
    });
    $('video-search').addEventListener('input', () => { page = 1; renderLibrary(); });
    $('clear-video-search').addEventListener('click', () => {
        $('video-search').value = '';
        page = 1;
        renderLibrary();
        $('video-search').focus();
    });
    for (const id of ['rating-filter', 'duration-filter', 'resolution-filter', 'attention-filter']) $(id).addEventListener('change', () => { page = 1; renderLibrary(); });
    function clearLibraryFilters(clearSearch = false) {
        for (const id of ['rating-filter', 'duration-filter', 'resolution-filter']) $(id).value = 'all';
        $('attention-filter').value = 'exclude';
        $('video-sort').value = 'default';
        if (clearSearch) $('video-search').value = '';
        page = 1;
        renderLibrary();
    }
    $('clear-library-filters').addEventListener('click', () => clearLibraryFilters());
    $('clear-search-filters').addEventListener('click', () => clearLibraryFilters(true));
    $('video-sort').addEventListener('change', () => { page = 1; renderLibrary(); });
    $('untagged-only').addEventListener('change', () => {
        if ($('untagged-only').checked) {
            tagModes.clear();
        }
        page = 1;
        render();
    });
    function changePage(value) {
        const number = Number(value);
        if (!Number.isInteger(number)) return;
        page = number;
        renderLibrary();
    }
    $('page-number').addEventListener('change', () => changePage($('page-number').value));
    $('page-number').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') { event.preventDefault(); changePage($('page-number').value); }
    });
    $('page-slider').addEventListener('input', () => changePage($('page-slider').value));
    $('first-page').addEventListener('click', () => changePage(1));
    $('last-page').addEventListener('click', () => changePage($('page-number').max));
    async function savePagination() {
        const sequence = ++paginationSequence;
        const choice = { page_size: Number($('videos-per-page').value), fill_last_row: $('fill-last-row').checked };
        pendingPagination = choice;
        page = 1;
        renderLibrary();
        await mutate('pagination_save', choice);
        if (sequence === paginationSequence) {
            pendingPagination = null;
            renderLibrary();
        }
    }
    $('videos-per-page').addEventListener('change', savePagination);
    $('fill-last-row').addEventListener('change', savePagination);
    new ResizeObserver(() => {
        if (!state.settings) return;
        const columns = Math.max(1, getComputedStyle($('video-grid')).gridTemplateColumns.split(' ').filter(Boolean).length);
        if (columns !== gridColumns) renderLibrary();
    }).observe($('video-grid'));
    $('previous-page').addEventListener('click', () => { page--; renderLibrary(); });
    $('next-page').addEventListener('click', () => { page++; renderLibrary(); });
    $('select-results').addEventListener('click', () => { visibleVideos().forEach((video) => selected.add(video.id)); renderLibrary(); });
    $('clear-selection').addEventListener('click', () => { selected.clear(); renderLibrary(); });
    $('play-results').addEventListener('click', () => startQueue(visibleVideos().map((video) => video.id)));
    $('previous-video').addEventListener('click', () => advance(-1));
    $('next-video').addEventListener('click', () => advance(1));
    $('edit-playing').addEventListener('click', () => editVideo(currentVideoId()));
    $('playlist-playing').addEventListener('click', () => openAssignments(currentVideoId(), 'playlists'));
    $('assignment-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = event.submitter;
        if (submit) submit.disabled = true;
        try {
            if (await mutate(assignmentKind === 'tags' ? 'video_tags_save' : 'video_playlists_add', { video: assignmentVideo, choices: chosenValues('assignment-choices') }, 'assignment-dialog')) modal('assignment-dialog').hide();
        } finally { if (submit) submit.disabled = false; }
    });
    $('play-pause').addEventListener('click', () => {
        if (player.paused) player.play().catch((error) => showError(error)); else player.pause();
    });
    player.addEventListener('play', () => { $('play-pause').firstElementChild.className = 'bi bi-pause-fill'; });
    player.addEventListener('pause', () => { $('play-pause').firstElementChild.className = 'bi bi-play-fill'; });
    player.addEventListener('ended', () => advance(1, true));
    player.addEventListener('error', () => {
        reportPlaybackError();
    });
    $('repeat-mode').addEventListener('change', () => { saveSettings(); renderPlayer(); });
    $('shuffle').addEventListener('click', () => {
        shuffle = !shuffle;
        if (shuffle) unshuffledQueue = [...queue];
        else if (unshuffledQueue) {
            const current = currentVideoId();
            queue = unshuffledQueue.filter((id) => videoMap.has(id));
            queueIndex = standaloneVideo ? -1 : queue.indexOf(current);
            unshuffledQueue = null;
        }
        if (shuffle && queue.length && standaloneVideo) queue = randomize(queue);
        else if (shuffle && queue.length) {
            const current = currentVideoId();
            const remaining = [...queue];
            const currentIndex = remaining.indexOf(current);
            if (currentIndex >= 0) remaining.splice(currentIndex, 1);
            queue = current ? [current, ...randomize(remaining)] : randomize(remaining);
            queueIndex = current ? 0 : -1;
        }
        saveSettings();
        saveQueue();
        renderPlayer();
    });
    $('clear-queue').addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        standaloneVideo = currentVideoId() || null;
        queue = [];
        queueIndex = -1;
        unshuffledQueue = null;
        renderPlayer();
        saveQueue();
    });
    $('reset-tag-filters').addEventListener('click', () => {
        tagModes.clear();
        $('untagged-only').checked = false;
        page = 1;
        render();
    });
    for (const id of ['playlists-content', 'tags-content']) {
        const panel = $(id);
        const toggle = document.querySelector('[data-bs-target="#' + id + '"]');
        try {
            const open = localStorage.getItem('framekeep-' + id) !== 'closed';
            panel.classList.toggle('show', open);
            toggle.setAttribute('aria-expanded', String(open));
        } catch (_) {}
        panel.addEventListener('shown.bs.collapse', () => { try { localStorage.setItem('framekeep-' + id, 'open'); } catch (_) {} });
        panel.addEventListener('hidden.bs.collapse', () => { try { localStorage.setItem('framekeep-' + id, 'closed'); } catch (_) {} });
    }
    $('manage-tags').addEventListener('click', () => openManager('tag'));
    $('manage-playlists').addEventListener('click', () => openManager('playlist'));
    $('video-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = event.submitter;
        if (submit) submit.disabled = true;
        try {
            if (await mutate('video_save', { id: editingVideo, name: $('video-name').value, tags: chosenValues('video-tags'), playlists: chosenValues('video-playlists') }, 'video-dialog')) modal('video-dialog').hide();
        } finally { if (submit) submit.disabled = false; }
    });
    $('manager-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = event.submitter;
        if (submit) submit.disabled = true;
        try {
            if (await mutate(managerKind + '_save', { name: $('manager-name').value }, 'manager-dialog')) {
                $('manager-name').value = '';
                renderManager();
            }
        } finally { if (submit) submit.disabled = false; }
    });
    $('batch-playlist').addEventListener('click', () => {
        clearError('batch-dialog');
        playlistOptions('batch-target');
        $('batch-count').textContent = selected.size + ' selected video(s)';
        $('batch-mode').value = 'add';
        $('batch-mode').querySelector('[value="move"]').disabled = !currentPlaylist;
        modal('batch-dialog').show();
    });
    $('batch-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = event.submitter;
        if (submit) submit.disabled = true;
        try {
            if (await mutate('playlist_batch', { target: Number($('batch-target').value), source: currentPlaylist, mode: $('batch-mode').value, videos: [...selected] }, 'batch-dialog')) {
                selected.clear();
                renderLibrary();
                modal('batch-dialog').hide();
            }
        } finally { if (submit) submit.disabled = false; }
    });
    $('open-sequence').addEventListener('click', () => {
        clearError('sequence-dialog');
        playlistOptions('sequence-playlist');
        renderSequence();
        modal('sequence-dialog').show();
    });
    $('sequence-add').addEventListener('click', () => {
        const id = Number($('sequence-playlist').value);
        if (!id) return;
        sequence.push(id);
        saveSettings();
        renderSequence();
    });
    $('sequence-clear').addEventListener('click', () => { sequence = []; saveSettings(); renderSequence(); });
    $('sequence-play').addEventListener('click', () => {
        const ids = sequence.flatMap((id) => {
            const playlist = state.playlists.find((item) => item.id === id);
            return playlist ? playlist.items.map((item) => item.video_id) : [];
        });
        if (!ids.length) {
            showError(new Error('These playlists have no videos yet.'), 'sequence-dialog');
            return;
        }
        startQueue(ids);
        modal('sequence-dialog').hide();
    });

    render();
    function sizePlayer() {
        const screen = player.parentElement;
        const top = screen.getBoundingClientRect().top + window.scrollY;
        screen.style.setProperty('--player-top', top + 'px');
    }
    window.addEventListener('resize', sizePlayer);
    new ResizeObserver(sizePlayer).observe(root);
    sizePlayer();
    api('playback_claim', { session: playbackSession }).then((payload) => {
        const saved = payload.state.playback;
        if (saved) {
            const volume = Math.max(0, Math.min(1, Number(saved.volume)));
            const muted = Boolean(Number(saved.muted));
            savedAudio = volume + ':' + muted;
            player.volume = volume;
            player.muted = muted;
        }
        playbackReady = true;
        $('autoplay-open').checked = Boolean(saved && Number(saved.autoplay));
        if (saved) {
            let previousQueue = [];
            try { previousQueue = JSON.parse(saved.queue); } catch (_) {}
            queue = Array.isArray(previousQueue) ? previousQueue.filter((id) => videoMap.has(id)) : [];
            queueIndex = Number(saved.queue_index);
            renderPlayer();
        }
        if (saved && saved.video_id && videoMap.has(saved.video_id)) {
            standaloneVideo = Number(saved.queue_index) < 0 || !queue.includes(saved.video_id) ? saved.video_id : null;
            queueIndex = Number(saved.queue_index);
            if (currentVideoId() !== saved.video_id) queueIndex = queue.indexOf(saved.video_id);
            playCurrent($('autoplay-open').checked, Number(saved.position));
        }
        if (payload.state.settings && Number(payload.state.settings.scan_on_start)) return scanLibrary();
        $('scan-status').textContent = 'Library ready.';
        refreshActivity(true);
    }).catch((error) => {
        $('scan-status').textContent = 'Library could not load.';
        showError(error);
    });
})();
