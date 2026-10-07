{{-- Goes in every layout's <head>: Khmer needs a font that has its glyphs; the system ones are the fallback while this loads. --}}
@if(app()->getLocale() === 'km')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&display=swap">
    <style>html[lang="km"]{font-family:"Noto Sans Khmer","Khmer UI","Leelawadee UI",Inter,"Segoe UI",system-ui,sans-serif}</style>
@endif
{{-- Strings that resources/js/app.js shows; it reads them from window.i18n and falls back to the English key. --}}
@php
    $jsStrings = [
        'Private', 'Public', 'Scheduled', 'Write the description…', 'Level :depth', 'under :name', 'Copied!',
        'Upload failed', 'Close', 'Uploading…', 'about :seconds s left', 'Upload complete',
        'Saving the media record. Audio conversion will continue in the background.', 'Almost done…',
        'Play', 'Stop', 'user', 'users', 'The server returned HTTP :status. Please try again.',
        'The connection was interrupted. Check the server and try again.',
    ];
    $i18n = [];
    foreach ($jsStrings as $jsString) {
        $i18n[$jsString] = __($jsString);
    }
@endphp
<script>window.i18n = @json($i18n);</script>
