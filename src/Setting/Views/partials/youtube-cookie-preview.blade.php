{{-- Lists the YouTube cookies in the file just chosen, before it is saved. Read in the browser only: nothing is sent, and the cookie values are never read out or shown. --}}
<div data-cookie-preview hidden style="margin-top:16px"
     data-labels="{{ json_encode([
         'title' => __('In this file'),
         'note' => __('YouTube cookies only. The values are never shown.'),
         'google' => __('Google sign-in cookies that will be saved as well: :count.'),
         'none' => __('There are no YouTube cookies in this file.'),
         'unreadable' => __('This file could not be read as a cookies export.'),
         'session' => __('Session'),
         'expired' => __('Expired'),
     ]) }}">
    <h4 style="margin:0 0 4px"><span data-cookie-title></span> <span class="muted" style="font-weight:400" data-cookie-count></span></h4>
    <small class="muted" style="display:block;margin-bottom:10px" data-cookie-note></small>
    <div class="data-table-wrap" style="max-height:340px;overflow:auto" data-cookie-table hidden>
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Domain') }}</th>
                    <th>{{ __('Include Subdomains') }}</th>
                    <th>{{ __('Path') }}</th>
                    <th>{{ __('Secure') }}</th>
                    <th>{{ __('Expiry') }}</th>
                    <th>{{ __('Name') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script>
    (() => {
        const input = document.querySelector('input[name="youtube_cookies_file"]');
        const box = document.querySelector('[data-cookie-preview]');
        if (!input || !box) return;

        const labels = JSON.parse(box.dataset.labels);
        const hostOf = domain => String(domain).replace(/^#HttpOnly_/, '').replace(/^\./, '').toLowerCase();
        const isYoutube = domain => { const host = hostOf(domain); return host === 'youtube.com' || host.endsWith('.youtube.com'); };
        const isGoogle = domain => { const host = hostOf(domain); return host === 'google.com' || host.endsWith('.google.com'); };

        // A Netscape cookies.txt: tab-separated, domain / subdomains / path / secure / expiry / name / value.
        // The value (column 7) is skipped on purpose.
        const fromNetscape = text => text.split(/\r?\n/)
            .map(line => line.trim())
            .filter(line => line && (!line.startsWith('#') || line.startsWith('#HttpOnly_')))
            .map(line => line.split('\t'))
            .filter(columns => columns.length >= 7)
            .map(columns => ({
                domain: columns[0].replace(/^#HttpOnly_/, ''),
                subdomains: columns[1].toUpperCase() === 'TRUE',
                path: columns[2],
                secure: columns[3].toUpperCase() === 'TRUE',
                expires: Math.max(0, parseInt(columns[4], 10) || 0),
                name: columns[5],
                httpOnly: columns[0].startsWith('#HttpOnly_'),
            }));

        // The JSON a browser extension exports: a list of cookies, or an object with a "cookies" list.
        const fromJson = text => {
            let data = JSON.parse(text);
            if (data && Array.isArray(data.cookies)) data = data.cookies;
            if (!Array.isArray(data)) throw new Error('not a cookie list');

            return data.filter(cookie => cookie && cookie.name && cookie.domain).map(cookie => {
                const domain = String(cookie.domain);
                const hostOnly = !!cookie.hostOnly && !domain.startsWith('.');
                const expires = cookie.session ? 0 : Number(cookie.expirationDate ?? cookie.expires ?? 0);

                return {
                    domain: hostOnly || domain.startsWith('.') ? domain : '.' + domain,
                    subdomains: !hostOnly,
                    path: cookie.path || '/',
                    secure: !!cookie.secure,
                    expires: expires > 0 ? Math.floor(expires) : 0,
                    name: String(cookie.name),
                    httpOnly: !!cookie.httpOnly,
                };
            });
        };

        const cell = (row, content) => {
            const td = row.insertCell();
            if (content instanceof Node) td.append(content); else td.textContent = content;
            return td;
        };

        const badge = (tone, text) => {
            const span = document.createElement('span');
            span.className = 'badge ' + tone;
            span.textContent = text;
            return span;
        };

        const date = seconds => new Date(seconds * 1000).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });

        const show = (title, count, note, cookies) => {
            const tbody = box.querySelector('tbody');
            tbody.replaceChildren();

            for (const cookie of cookies) {
                const row = tbody.insertRow();
                cell(row, cookie.domain);
                cell(row, cookie.subdomains ? 'TRUE' : 'FALSE');
                cell(row, cookie.path);
                cell(row, cookie.secure ? 'TRUE' : 'FALSE');

                const expiry = cell(row, '');
                expiry.title = String(cookie.expires);
                if (cookie.expires === 0) expiry.append(badge('slate', labels.session));
                else if (cookie.expires < Date.now() / 1000) expiry.append(badge('pink', labels.expired + ' · ' + date(cookie.expires)));
                else expiry.append(date(cookie.expires));

                const name = cell(row, '');
                const bold = document.createElement('b');
                bold.textContent = cookie.name;
                name.append(bold);
                if (cookie.httpOnly) name.append(' ', badge('slate', 'HttpOnly'));
            }

            box.querySelector('[data-cookie-title]').textContent = title;
            box.querySelector('[data-cookie-count]').textContent = count;
            box.querySelector('[data-cookie-note]').textContent = note;
            box.querySelector('[data-cookie-table]').hidden = cookies.length === 0;
            box.hidden = false;
        };

        input.addEventListener('change', async () => {
            const file = input.files[0];
            if (!file) { box.hidden = true; return; }

            try {
                const text = (await file.text()).replace(/^﻿/, '');
                const all = text.trimStart().startsWith('[') || text.trimStart().startsWith('{') ? fromJson(text) : fromNetscape(text);
                const youtube = all.filter(cookie => isYoutube(cookie.domain));
                const google = all.filter(cookie => isGoogle(cookie.domain)).length;

                if (youtube.length === 0) return show(labels.title, '', labels.none, []);

                show(labels.title, '(' + youtube.length + ')', google ? labels.google.replace(':count', google) : labels.note, youtube);
            } catch (error) {
                show(labels.title, '', labels.unreadable, []);
            }
        });
    })();
</script>
