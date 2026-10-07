// Plays a pasted YouTube / Facebook link as audio only: the site's own player sits off-screen, so only the sound
// is heard. Nothing is downloaded or saved — the link is streamed by YouTube / Facebook straight to the browser.

const YOUTUBE_ID = /^[\w-]{11}$/;

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

/** What a link needs to be played — `{ kind: 'youtube', id }` or `{ kind: 'facebook', link }` — or null for anything else (a plain audio file or stream). */
export const socialSource = link => {
    const url = parse(link);
    if (!url || !['http:', 'https:'].includes(url.protocol)) return null;

    const id = youtubeId(url);
    if (id) return { kind: 'youtube', id };
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
    stopCurrent = source.kind === 'youtube' ? playYouTube(source.id, handlers) : playFacebook(source.link);
};
