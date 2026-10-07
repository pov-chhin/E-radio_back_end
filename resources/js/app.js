import './bootstrap';
import { createApp } from 'vue';
import Quill from 'quill';
import Hls from 'hls.js';
import 'quill/dist/quill.snow.css';
import TopbarMenus from './components/TopbarMenus.vue';
import { playSocial, socialSource, stopSocial } from './social-player';

// The page prints the strings the scripts show (see partials/locale-head.blade.php); a missing key falls back to English.
const t = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replace(`:${name}`, value), window.i18n?.[key] ?? key);
const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];

// Only Safari plays HLS (.m3u8) through a bare <audio src>; everywhere else hls.js has to feed the segments in.
const hlsPlayers = new WeakMap();
const releaseStream = audioEl => {
    hlsPlayers.get(audioEl)?.destroy();
    hlsPlayers.delete(audioEl);
};
const setStreamSource = (audioEl, url, onFatalError = () => {}) => {
    releaseStream(audioEl);
    const isHls = /\.m3u8(\?|#|$)/i.test(url);
    if (!isHls || audioEl.canPlayType('application/vnd.apple.mpegurl') || !Hls.isSupported()) {
        audioEl.src = url;
        return;
    }
    const hls = new Hls();
    hls.on(Hls.Events.ERROR, (_event, data) => {
        if (!data.fatal) return;
        releaseStream(audioEl);
        onFatalError();
    });
    hls.loadSource(url);
    hls.attachMedia(audioEl);
    hlsPlayers.set(audioEl, hls);
};
const clearStreamSource = audioEl => {
    releaseStream(audioEl);
    audioEl.removeAttribute('src');
};

const topbarMenusRoot = qs('#topbar-menus');
if (topbarMenusRoot) {
    createApp(TopbarMenus, {
        nav: JSON.parse(topbarMenusRoot.dataset.nav || '[]'),
        notifications: JSON.parse(topbarMenusRoot.dataset.notifications || '[]'),
        unreadCount: Number(topbarMenusRoot.dataset.unreadCount || 0),
        markAllReadUrl: topbarMenusRoot.dataset.markAllReadUrl,
        notificationsIndexUrl: topbarMenusRoot.dataset.notificationsIndexUrl,
    }).mount(topbarMenusRoot);
}

const sidebar = qs('#sidebar');
qs('[data-menu-toggle]')?.addEventListener('click', () => sidebar?.classList.toggle('open'));

// Checking a tag-tree parent's own checkbox shouldn't also collapse/expand it.
qsa('.tag-tree-node > summary label').forEach(label => label.addEventListener('click', event => event.stopPropagation()));

// Filter the media editor's tag tree while retaining ancestors of matching tags.
const tagSearch = qs('[data-tag-search]');
const searchableTagTree = qs('[data-tag-tree]');
if (tagSearch && searchableTagTree) {
    const visitTagNode = (element, term) => {
        if (!element.matches('details')) {
            const matches = !term || element.textContent.toLowerCase().includes(term);
            element.hidden = !matches;
            return matches;
        }

        const summary = element.querySelector(':scope > summary');
        const children = element.querySelector(':scope > .tag-tree-children');
        const ownMatches = !term || summary?.textContent.toLowerCase().includes(term);
        const childMatches = children
            ? [...children.children].map(child => visitTagNode(child, term)).some(Boolean)
            : false;
        const matches = ownMatches || childMatches;

        element.hidden = !matches;
        if (term && childMatches) element.open = true;
        return matches;
    };

    tagSearch.addEventListener('input', () => {
        const term = tagSearch.value.trim().toLowerCase();
        const hasMatches = [...searchableTagTree.children]
            .map(child => visitTagNode(child, term))
            .some(Boolean);
        qs('[data-tag-search-empty]')?.toggleAttribute('hidden', hasMatches);
    });
}

// Tree view / Column view toggle for the tag picker.
qsa('[data-tag-view-tab]').forEach(button => button.addEventListener('click', () => {
    qsa('[data-tag-view-tab]').forEach(tab => tab.classList.toggle('active', tab === button));
    qsa('[data-tag-view-panel]').forEach(panel => { panel.hidden = panel.dataset.tagViewPanel !== button.dataset.tagViewTab; });
}));

// Upload / Paste URL toggle for image fields (artist photo/cover, radio channel cover, …).
// Disabling the inactive input keeps it out of the form submission entirely.
qsa('[data-image-mode-tab]').forEach(button => button.addEventListener('click', () => {
    const field = button.dataset.imageField;
    const group = qsa(`[data-image-field="${field}"]`);
    group.filter(el => el.matches('[data-image-mode-tab]')).forEach(tab => tab.classList.toggle('active', tab === button));
    group.filter(el => el.matches('[data-image-mode-panel]')).forEach(panel => {
        const isActive = panel.dataset.imageModePanel === button.dataset.imageModeTab;
        panel.hidden = !isActive;
        panel.disabled = !isActive;
    });
}));

// Preview a newly selected featured image before the media form is submitted.
const thumbnailInput = qs('[data-thumbnail-input]');
const thumbnailPreview = qs('[data-thumbnail-preview]');
if (thumbnailInput && thumbnailPreview) {
    const originalThumbnail = thumbnailPreview.getAttribute('src') || '';
    let previewUrl;

    thumbnailInput.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        const file = thumbnailInput.files?.[0];

        if (file) {
            previewUrl = URL.createObjectURL(file);
            thumbnailPreview.src = previewUrl;
            thumbnailPreview.hidden = false;
            return;
        }

        previewUrl = undefined;
        thumbnailPreview.src = originalThumbnail;
        thumbnailPreview.hidden = !originalThumbnail;
    });
}

// Read the duration straight off a newly selected audio/video file so the admin
// doesn't have to time it by hand — the queue worker overwrites this with the
// ffprobe-measured value once conversion finishes, this is just the instant guess.
const streamInput = qs('#stream');
const durationInput = qs('#duration_seconds');
if (streamInput && durationInput) {
    streamInput.addEventListener('change', () => {
        const file = streamInput.files?.[0];
        if (!file) return;

        const probe = document.createElement('video');
        probe.preload = 'metadata';
        const cleanup = () => URL.revokeObjectURL(probe.src);
        probe.addEventListener('loadedmetadata', () => {
            if (Number.isFinite(probe.duration) && probe.duration > 0) durationInput.value = Math.round(probe.duration);
            cleanup();
        }, { once: true });
        probe.addEventListener('error', cleanup, { once: true });
        probe.src = URL.createObjectURL(file);
    });
}

// Normalize common title-like input ("My new song") before validation so a
// large media file is never uploaded only to be rejected for an invalid slug.
qs('[data-slug-input]')?.addEventListener('blur', event => {
    event.target.value = event.target.value
        .trim()
        .replace(/[^A-Za-z0-9_-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
});

// Reveal the "Publish at" field only while Status is set to Scheduled, and
// keep the editor topbar's status badge in sync with the selected value.
const STATUS_META = {
    private: { label: 'Private', tone: 'slate' },
    public: { label: 'Public', tone: 'green' },
    scheduled: { label: 'Scheduled', tone: 'amber' },
};
qs('[data-schedule-status]')?.addEventListener('change', event => {
    qs('[data-schedule-field]')?.toggleAttribute('hidden', event.target.value !== 'scheduled');
    const badge = qs('[data-status-badge]');
    const meta = STATUS_META[event.target.value];
    if (badge && meta) {
        badge.textContent = t(meta.label);
        badge.className = `badge ${meta.tone}`;
    }
});

// "Save draft" always stashes the item as private, regardless of whatever
// the Status select is currently set to — a quick way out without having
// to touch the sidebar.
qs('[data-save-draft]')?.addEventListener('click', () => {
    const statusSelect = qs('[data-schedule-status]');
    if (statusSelect) statusSelect.value = 'private';
    qs('[data-schedule-field]')?.setAttribute('hidden', '');
});

// Rich text editor (Quill) for the media content description — the visible
// editor writes into a hidden textarea so the form still just posts plain HTML.
qsa('[data-rich-text]').forEach(container => {
    const source = container.parentElement.querySelector('[data-rich-text-source]');
    if (!source) return;

    const quill = new Quill(container, {
        theme: 'snow',
        placeholder: t('Write the description…'),
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline', 'link'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote'],
                ['clean'],
            ],
        },
    });

    if (source.value) quill.clipboard.dangerouslyPasteHTML(source.value);

    const sync = () => { source.value = quill.getText().trim() ? quill.root.innerHTML : ''; };
    quill.on('text-change', sync);
    container.closest('form')?.addEventListener('submit', sync);
});

// Column-browser tag picker: each checked node with children opens the next column, like a Finder column view.
// Kept in sync with the tree view's checkboxes (same `tags[]`/value pairs) in both directions.
const tagColumns = qs('[data-tag-columns]');
if (tagColumns) {
    const tree = JSON.parse(tagColumns.dataset.tagTree || '[]');
    const selected = new Set(JSON.parse(tagColumns.dataset.selectedTags || '[]').map(String));

    const syncOtherCheckboxes = (id, checked) => {
        qsa(`input[name="tags[]"][value="${id}"]`).forEach(input => { if (input.checked !== checked) input.checked = checked; });
    };

    const setSelected = (id, checked, rerender = true) => {
        if (checked) selected.add(String(id)); else selected.delete(String(id));
        syncOtherCheckboxes(id, checked);
        if (rerender) renderTagColumns();
    };

    function renderTagColumns() {
        tagColumns.innerHTML = '';
        let level = tree;
        let depth = 1;

        while (level.length) {
            const column = document.createElement('div');
            column.className = 'tag-column';

            const label = document.createElement('p');
            label.className = 'tag-column-label';
            label.textContent = t('Level :depth', { depth });
            column.appendChild(label);

            level.forEach(node => {
                const row = document.createElement('label');
                row.className = 'tag-column-item';

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = 'tags[]';
                input.value = node.id;
                input.checked = selected.has(String(node.id));
                input.addEventListener('change', () => setSelected(node.id, input.checked));
                row.appendChild(input);

                const text = document.createElement('span');
                text.textContent = node.name;
                if (node._parentName) {
                    const hint = document.createElement('small');
                    hint.textContent = t('under :name', { name: node._parentName });
                    text.appendChild(document.createElement('br'));
                    text.appendChild(hint);
                }
                row.appendChild(text);

                const count = document.createElement('small');
                count.className = 'tag-column-count';
                count.textContent = (node.count ?? 0).toLocaleString();
                row.appendChild(count);

                column.appendChild(row);
            });

            tagColumns.appendChild(column);

            const next = [];
            level.forEach(node => {
                if (selected.has(String(node.id)) && node.children?.length) {
                    node.children.forEach(child => next.push({ ...child, _parentName: node.name }));
                }
            });
            level = next;
            depth++;
        }
    }

    renderTagColumns();

    qsa('.tag-tree input[type=checkbox]').forEach(input => input.addEventListener('change', () => setSelected(input.value, input.checked)));
}

// Dropdown toggle/search behavior for the topbar's search + notifications panels
// now lives in resources/js/components/TopbarMenus.vue. Other dropdown-anchors
// (e.g. the station switcher in the sidebar) still use the [data-dropdown-toggle]
// pattern below.
qsa('[data-dropdown-toggle]').forEach(toggle => toggle.addEventListener('click', event => {
    event.stopPropagation();
    const menu = toggle.closest('.dropdown-anchor')?.querySelector('[data-dropdown]');
    if (!menu) return;
    const isOpen = menu.classList.contains('open');
    qsa('[data-dropdown].open').forEach(open => { open.classList.remove('open'); open.previousElementSibling?.setAttribute?.('aria-expanded', 'false'); });
    if (!isOpen) {
        menu.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        qs('[data-search-input]', menu)?.focus();
    }
}));
document.addEventListener('click', () => qsa('[data-dropdown].open').forEach(open => open.classList.remove('open')));
document.addEventListener('keydown', event => { if (event.key === 'Escape') qsa('[data-dropdown].open').forEach(open => open.classList.remove('open')); });

const previewAudio = qs('[data-preview-audio]');
const resetPreviewButtons = () => qsa('[data-preview-toggle].is-playing').forEach(button => { button.classList.remove('is-playing'); button.innerHTML = `<i class="fa fa-play"></i> ${t('Play')}`; });
const showStopButton = button => {
    button.classList.add('is-playing');
    button.innerHTML = `<i class="fa fa-stop"></i> ${t('Stop')}`;
};
// A YouTube / Facebook / Vimeo link plays in the site's own player, off-screen (audio only), not through the <audio> element.
let socialPlaying = false;
const stopPreviewAudio = () => { previewAudio?.pause(); if (previewAudio) clearStreamSource(previewAudio); };
qsa('[data-preview-toggle]').forEach(button => button.addEventListener('click', () => {
    const wasPlaying = button.classList.contains('is-playing');
    resetPreviewButtons();
    socialPlaying = false;
    stopSocial();
    const social = socialSource(button.dataset.streamUrl);
    if (wasPlaying) { stopPreviewAudio(); return; }
    if (social) {
        stopPreviewAudio();
        const finish = () => { socialPlaying = false; resetPreviewButtons(); };
        socialPlaying = true;
        playSocial(social, {
            onEnd: finish,
            onError: () => { finish(); window.alert(t('This link cannot be played here — some videos do not allow embedded playback.')); },
        });
        showStopButton(button);
        return;
    }
    if (!previewAudio) return;
    setStreamSource(previewAudio, button.dataset.streamUrl, resetPreviewButtons);
    previewAudio.play().catch(() => resetPreviewButtons());
    showStopButton(button);
}));
// stopPreviewAudio() fires a late "pause" — it must not reset the button of a link that has just started playing.
previewAudio?.addEventListener('pause', () => { if (!socialPlaying) resetPreviewButtons(); });
previewAudio?.addEventListener('ended', resetPreviewButtons);
previewAudio?.addEventListener('error', resetPreviewButtons);

const audio = qs('[data-audio]');
const streamUrl = document.body?.dataset.streamUrl;
let playing = false;
const setPlaying = (state) => {
    playing = state;
    qsa('[data-play-toggle]').forEach(button => button.classList.toggle('playing', state));
    qs('.player-controls')?.classList.toggle('playing', state);
};
qsa('[data-play-toggle]').forEach(button => button.addEventListener('click', async () => {
    if (!audio) return;
    if (!audio.src && streamUrl) setStreamSource(audio, streamUrl, () => setPlaying(false));
    try {
        if (audio.paused) { await audio.play(); setPlaying(true); }
        else { audio.pause(); setPlaying(false); }
    } catch { setPlaying(false); }
}));
audio?.addEventListener('pause', () => setPlaying(false));
audio?.addEventListener('playing', () => setPlaying(true));
qs('[data-volume]')?.addEventListener('input', event => { if (audio) audio.volume = event.target.value / 100; });

const chart = qs('#audienceChart');
if (chart) {
    const values = chart.dataset.values.split(',').map(Number);
    const draw = () => {
        const dpr = window.devicePixelRatio || 1;
        const width = chart.clientWidth, height = Math.min(chart.getAttribute('height') || 220, 220);
        chart.width = width * dpr; chart.height = height * dpr;
        const ctx = chart.getContext('2d'); ctx.scale(dpr, dpr);
        const pad = 8, max = Math.max(...values) * 1.12, min = Math.min(...values) * .72;
        const points = values.map((value, index) => ({x: pad + index * ((width - pad * 2) / (values.length - 1)), y: height - pad - ((value - min) / (max - min)) * (height - pad * 2)}));
        const gradient = ctx.createLinearGradient(0, 0, 0, height); gradient.addColorStop(0, 'rgba(250,73,101,.28)'); gradient.addColorStop(1, 'rgba(250,73,101,0)');
        ctx.beginPath(); ctx.moveTo(points[0].x, height); points.forEach(point => ctx.lineTo(point.x, point.y)); ctx.lineTo(points.at(-1).x, height); ctx.closePath(); ctx.fillStyle = gradient; ctx.fill();
        ctx.beginPath(); points.forEach((point, i) => i ? ctx.lineTo(point.x, point.y) : ctx.moveTo(point.x, point.y)); ctx.strokeStyle = '#fa4965'; ctx.lineWidth = 2; ctx.lineJoin = 'round'; ctx.stroke();
        const point = points.at(-1); ctx.beginPath(); ctx.arc(point.x, point.y, 4, 0, Math.PI*2); ctx.fillStyle='#fff'; ctx.fill(); ctx.lineWidth=2; ctx.strokeStyle='#fa4965'; ctx.stroke();
    };
    draw(); window.addEventListener('resize', draw);
}

qsa('[data-theme]').forEach(button => button.addEventListener('click', () => {
    qsa('[data-theme]').forEach(item => item.classList.remove('active'));
    button.classList.add('active'); qs('.public-player')?.classList.toggle('light', button.dataset.theme === 'light');
}));
qsa('[data-accent]').forEach(button => button.addEventListener('click', () => qs('.public-player')?.style.setProperty('--accent', button.dataset.accent)));
qs('[data-copy]')?.addEventListener('click', async event => { const code = qs('.code-box code')?.textContent; if (code) await navigator.clipboard.writeText(code); event.currentTarget.textContent = t('Copied!'); });
qsa('[data-settings-tab]').forEach(button => button.addEventListener('click', () => {
    qsa('[data-settings-tab]').forEach(tab => tab.classList.toggle('active', tab === button));
    qsa('[data-settings-panel]').forEach(panel => panel.hidden = panel.dataset.settingsPanel !== button.dataset.settingsTab);
}));
// Storage settings: the S3 credential fields only matter while "S3-compatible storage" is the chosen type.
const syncStorageDriver = () => {
    const useS3 = qs('[data-storage-driver]:checked')?.value === 's3';
    qsa('[data-storage-s3]').forEach(panel => { panel.hidden = !useS3; });
};
qsa('[data-storage-driver]').forEach(radio => radio.addEventListener('change', syncStorageDriver));
qsa('[data-toast] button').forEach(button => button.addEventListener('click', () => button.parentElement.remove()));

// A normal multipart form gives no feedback while a large MP3 is uploading.
// XHR keeps the same Laravel form/redirect flow while exposing byte progress.
qsa('[data-media-upload-form]').forEach(form => form.addEventListener('submit', event => {
    const selectedFiles = qsa('input[type="file"]', form).flatMap(input => [...(input.files || [])]);
    if (!selectedFiles.length || form.dataset.uploading === 'true') return;

    event.preventDefault();
    form.dataset.uploading = 'true';

    const overlay = qs('[data-upload-progress]', form);
    const card = overlay?.querySelector('.upload-progress-card');
    const title = qs('[data-upload-title]', overlay);
    const message = qs('[data-upload-message]', overlay);
    const track = qs('[data-upload-track]', overlay);
    const bar = qs('[data-upload-bar]', overlay);
    const percent = qs('[data-upload-percent]', overlay);
    const size = qs('[data-upload-size]', overlay);
    const cancel = qs('[data-upload-cancel]', overlay);
    const submitButtons = qsa('button[type="submit"]', form);
    const request = new XMLHttpRequest();
    const startedAt = Date.now();
    const formatBytes = bytes => {
        if (bytes < 1024) return `${bytes} B`;
        const units = ['KB', 'MB', 'GB'];
        let value = bytes / 1024, unit = 0;
        while (value >= 1024 && unit < units.length - 1) { value /= 1024; unit++; }
        return `${value.toFixed(value >= 10 ? 1 : 2)} ${units[unit]}`;
    };
    const reset = () => {
        form.dataset.uploading = 'false';
        submitButtons.forEach(button => { button.disabled = false; });
    };
    const showError = text => {
        reset();
        card?.classList.add('is-error');
        track?.classList.remove('is-saving');
        if (title) title.textContent = t('Upload failed');
        if (message) message.textContent = text;
        if (cancel) cancel.textContent = t('Close');
    };

    submitButtons.forEach(button => { button.disabled = true; });
    overlay.hidden = false;
    request.open((form.method || 'POST').toUpperCase(), form.action);
    request.responseType = 'text';

    request.upload.addEventListener('progress', progress => {
        if (!progress.lengthComputable) {
            if (size) size.textContent = t('Uploading…');
            return;
        }
        const value = Math.min(100, Math.round((progress.loaded / progress.total) * 100));
        const seconds = Math.max(1, (Date.now() - startedAt) / 1000);
        const speed = progress.loaded / seconds;
        const remaining = speed > 0 ? Math.ceil((progress.total - progress.loaded) / speed) : 0;
        if (bar) bar.style.width = `${value}%`;
        if (percent) percent.textContent = `${value}%`;
        if (track) track.setAttribute('aria-valuenow', String(value));
        if (size) size.textContent = `${formatBytes(progress.loaded)} / ${formatBytes(progress.total)}${remaining > 0 ? ` · ${t('about :seconds s left', { seconds: remaining })}` : ''}`;
    });

    request.upload.addEventListener('load', () => {
        track?.classList.add('is-saving');
        if (title) title.textContent = t('Upload complete');
        if (message) message.textContent = t('Saving the media record. Audio conversion will continue in the background.');
        if (percent) percent.textContent = '100%';
        if (size) size.textContent = t('Almost done…');
        if (cancel) cancel.disabled = true;
    });

    request.addEventListener('load', () => {
        if (request.status < 200 || request.status >= 400) {
            showError(t('The server returned HTTP :status. Please try again.', { status: request.status }));
            return;
        }

        const destination = request.responseURL || form.action;
        const successfulIndex = /\/admin\/media-contents\/?$/.test(new URL(destination).pathname);
        if (successfulIndex) {
            window.location.assign(destination);
            return;
        }

        // Laravel followed a validation redirect. Render that response so its
        // field errors and old input remain visible instead of losing them.
        document.open();
        document.write(request.responseText);
        document.close();
    });
    request.addEventListener('error', () => showError(t('The connection was interrupted. Check the server and try again.')));
    request.addEventListener('abort', () => {
        reset();
        overlay.hidden = true;
    });
    cancel?.addEventListener('click', () => {
        if (request.readyState !== XMLHttpRequest.DONE && !card?.classList.contains('is-error')) request.abort();
        else overlay.hidden = true;
    }, { once: true });

    request.send(new FormData(form));
}));

const sessionClock = qs('[data-session-clock]');
if (sessionClock && !sessionClock.textContent.includes('-')) {
    let seconds = sessionClock.textContent.split(':').reduce((sum, part) => sum * 60 + Number(part), 0);
    setInterval(() => { seconds++; sessionClock.textContent = [Math.floor(seconds/3600), Math.floor(seconds/60)%60, seconds%60].map(v => String(v).padStart(2,'0')).join(':'); }, 1000);
}

// Dashboard "Visitors by country" map — loaded on demand so no other admin page ships the map library.
const countryMap = qs('[data-country-map]');
if (countryMap) import('./dashboard-map.js').then(({ mountCountryMap }) => mountCountryMap(countryMap));
