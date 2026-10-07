// Plays a pasted YouTube / Facebook / Vimeo link as audio only: the site's own player sits off-screen, so only the
// sound is heard. Nothing is downloaded or saved — the link is streamed by the site straight to the browser.

const YOUTUBE_ID = /^[\w-]{11}$/;
// An unlisted Vimeo video carries a hash after its id (vimeo.com/123456789/abcdef1234), or as ?h= on the player link.
const VIMEO_HASH = /^[0-9a-f]{8,}$/i;

const parse = link => { try { return new URL(link); } catch { return null; } };
const hostOf = url => url.hostname.toLowerCase();
const isHost = (url, domain) => hostOf(url) === domain || hostOf(url).endsWith(`.${domain}`);

const youtubeId = url => {
    let id = null;
    if (hostOf(url) === 'youtu.be') id = url.pathname.split('/')[1];
    else if (isHost(url, 'youtube.com') || isHost(url, 'youtube-nocookie.com')) {
        id = url.pathname === '/watch' ? url.searchParams.get('v') : url.pathname.match(/^\/(?:embed|shorts|live|v)\/([^/?#]+)/)?.[1];
    }
    return YOUTUBE_ID.test(id ?? '') ? id : null;
};

// vimeo.com/123, /123/hash, /channels/name/123, /groups/name/videos/123, /album|showcase/9/video/123, player.vimeo.com/video/123
const vimeoVideo = url => {
    if (!isHost(url, 'vimeo.com')) return null;

    const parts = url.pathname.split('/').filter(Boolean);
    const numeric = part => /^\d+$/.test(part ?? '');
    const marked = parts.findIndex((part, index) => /^videos?$/.test(part) && numeric(parts[index + 1]));
    // A showcase or album page is a list, not a video — its own number is not a video id.
    const index = marked >= 0 ? marked + 1 : (['album', 'showcase'].includes(parts[0]) ? -1 : parts.findIndex(numeric));
    if (index < 0) return null;

    const hash = url.searchParams.get('h') ?? (VIMEO_HASH.test(parts[index + 1] ?? '') ? parts[index + 1] : null);

    return { id: parts[index], hash };
};

/** What a link needs to be played — `{ kind: 'youtube', id }`, `{ kind: 'vimeo', id, hash }` or `{ kind: 'facebook', link }` — or null for anything else (a plain audio file or stream). */
export const socialSource = link => {
    const url = parse(link);
    if (!url || !['http:', 'https:'].includes(url.protocol)) return null;

    const id = youtubeId(url);
    if (id) return { kind: 'youtube', id };

    const vimeo = vimeoVideo(url);
    if (vimeo) return { kind: 'vimeo', ...vimeo };

    if (isHost(url, 'facebook.com') || hostOf(url) === 'fb.watch') return { kind: 'facebook', link: url.href };

    return null;
};

// Off-screen rather than display:none — browsers won't reliably play media in a hidden frame.
const stage = () => {
    let element = document.querySelector('[data-social-stage]');
    if (!element) {
        element = document.createElement('div');
        element.dataset.socialStage = '';
        element.setAttribute('aria-hidden', 'true');
        element.style.cssText = 'position:fixed;left:-9999px;top:0;width:320px;height:180px;overflow:hidden;pointer-events:none';
        document.body.append(element);
    }
    return element;
};

let youtubeApi = null;
const loadYouTubeApi = () => youtubeApi ??= new Promise((resolve, reject) => {
    if (window.YT?.Player) return resolve(window.YT);
    const previous = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = () => { previous?.(); resolve(window.YT); };
    const script = document.createElement('script');
    script.src = 'https://www.youtube.com/iframe_api';
    script.onerror = () => { youtubeApi = null; script.remove(); reject(new Error('The YouTube player could not be loaded.')); };
    document.head.append(script);
});

const playYouTube = (id, { onEnd, onError }) => {
    let player = null;
    let stopped = false;
    loadYouTubeApi().then(YT => {
        if (stopped) return;
        const holder = document.createElement('div');
        stage().append(holder);
        player = new YT.Player(holder, {
            width: 320, height: 180, videoId: id,
            playerVars: { autoplay: 1, controls: 0, playsinline: 1, rel: 0, origin: location.origin },
            events: {
                onReady: event => event.target.playVideo(),
                onStateChange: event => { if (event.data === YT.PlayerState.ENDED) onEnd(); },
                // Embedding switched off by the video's owner, a private or removed video, …
                onError: () => onError(),
            },
        });
    }).catch(() => onError());

    return () => {
        stopped = true;
        try { player?.destroy(); } catch { /* the frame is already gone */ }
    };
};

let vimeoApi = null;
const loadVimeoApi = () => vimeoApi ??= new Promise((resolve, reject) => {
    if (window.Vimeo?.Player) return resolve(window.Vimeo);
    const script = document.createElement('script');
    script.src = 'https://player.vimeo.com/api/player.js';
    script.onload = () => resolve(window.Vimeo);
    script.onerror = () => { vimeoApi = null; script.remove(); reject(new Error('The Vimeo player could not be loaded.')); };
    document.head.append(script);
});

// A private video, or one whose owner restricts where it may be embedded, shows an error page inside the frame
// and never starts — so a video that has not begun playing after this long counts as unplayable.
const VIMEO_START_TIMEOUT = 15000;

const playVimeo = ({ id, hash }, { onEnd, onError }) => {
    let frame = null;
    let timer = null;
    let stopped = false;
    loadVimeoApi().then(Vimeo => {
        if (stopped) return;
        frame = document.createElement('iframe');
        frame.src = `https://player.vimeo.com/video/${id}?${new URLSearchParams({ ...(hash ? { h: hash } : {}), autoplay: '1', controls: '0', playsinline: '1' })}`;
        frame.allow = 'autoplay; encrypted-media';
        frame.width = '320';
        frame.height = '180';
        frame.title = '';
        stage().append(frame);

        const player = new Vimeo.Player(frame);
        timer = setTimeout(onError, VIMEO_START_TIMEOUT);
        player.on('playing', () => clearTimeout(timer));
        player.on('ended', onEnd);
        player.on('error', onError);
        player.ready().then(() => player.play()).catch(() => { /* autoplay can already have started it; a real failure trips the timeout */ });
    }).catch(() => onError());

    return () => {
        stopped = true;
        clearTimeout(timer);
        frame?.remove();
    };
};

// Facebook's video plugin reports nothing back to the page, so it has no "ended" or "failed" signal: it plays until stopped.
const playFacebook = link => {
    const frame = document.createElement('iframe');
    frame.src = `https://www.facebook.com/plugins/video.php?${new URLSearchParams({ href: link, show_text: 'false', autoplay: 'true', width: '320' })}`;
    frame.allow = 'autoplay; encrypted-media';
    frame.width = '320';
    frame.height = '180';
    frame.title = '';
    stage().append(frame);

    return () => frame.remove();
};

let stopCurrent = null;

/** Stops whatever social link is playing. */
export const stopSocial = () => {
    stopCurrent?.();
    stopCurrent = null;
};

/**
 * Starts playing a `socialSource()` result, stopping any earlier one.
 * `onEnd` fires when the track finishes on its own, `onError` when it can't be played.
 */
export const playSocial = (source, { onEnd = () => {}, onError = () => {} } = {}) => {
    stopSocial();
    const handlers = {
        onEnd: () => { stopSocial(); onEnd(); },
        onError: () => { stopSocial(); onError(); },
    };
    stopCurrent = {
        youtube: () => playYouTube(source.id, handlers),
        vimeo: () => playVimeo(source, handlers),
        facebook: () => playFacebook(source.link),
    }[source.kind]();
};
