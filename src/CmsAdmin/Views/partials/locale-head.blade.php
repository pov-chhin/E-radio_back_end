{{-- Goes in every layout's <head>: Khmer needs a font that has its glyphs. Khmer OS Battambang is the one set: it is on
     the Khmer computers that have the Khmer OS fonts, and Noto Sans Khmer (loaded here) stands in on the ones that
     don't while the system fonts cover the first moments. --}}
@if(app()->getLocale() === 'km')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&display=swap">
    {{-- The dashboard and the map tooltips set their own font (Nunito, which has no Khmer), and the description
         editor sets Helvetica on itself, so each of those is named here too. --}}
    <style>
        html[lang="km"],
        html[lang="km"] .dash,
        html[lang="km"] .jvm-tooltip,
        html[lang="km"] .ql-container,
        html[lang="km"] .ql-toolbar { font-family: "Khmer OS Battambang", "Noto Sans Khmer", "Khmer UI", "Leelawadee UI", Inter, "Segoe UI", system-ui, sans-serif; }
    </style>
@endif
{{-- Strings that resources/js/app.js shows; it reads them from window.i18n and falls back to the English key. --}}
@php
    $jsStrings = [
        'Private', 'Public', 'Scheduled', 'Write the description…', 'Level :depth', 'under :name', 'Copied!',
        'Upload failed', 'Close', 'Uploading…', 'about :seconds s left', 'Upload complete',
        'Saving the media record. Audio conversion will continue in the background.', 'Almost done…',
        'Play', 'Stop', 'This link cannot be played here — some videos do not allow embedded playback.', 'user', 'users', 'The server returned HTTP :status. Please try again.',
        'The connection was interrupted. Check the server and try again.',
    ];
    $i18n = [];
    foreach ($jsStrings as $jsString) {
        $i18n[$jsString] = __($jsString);
    }
@endphp
<script>window.i18n = @json($i18n);</script>
