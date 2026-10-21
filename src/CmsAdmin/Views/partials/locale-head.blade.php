{{-- Goes in every layout's <head>: Khmer needs a font that has its glyphs. Khmer OS Battambang is the one set: it is on
     the Khmer computers that have the Khmer OS fonts, and Noto Sans Khmer (loaded here) stands in on the ones that
     don't while the system fonts cover the first moments. --}}
{{-- The CMS's text font is Inter (the font stack in app.css names it first), from Google Fonts: until it was loaded here, every
     computer without Inter installed fell back to its system font. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
@if(app()->getLocale() === 'km')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&display=swap">
    {{-- The dashboard and the map tooltips set their own font (Nunito, which has no Khmer), and the description
         editor sets Helvetica on itself, so each of those is named here too. --}}
    <style>
        html[lang="km"],
        html[lang="km"] .dash,
        html[lang="km"] .jvm-tooltip,
        html[lang="km"] .ql-container,
        html[lang="km"] .ql-toolbar { font-family: "Khmer OS Battambang", "Noto Sans Khmer", "Khmer UI", "Leelawadee UI", Inter, "Segoe UI", system-ui, sans-serif; }

        /* Khmer is written in clusters (a consonant with its stacked subscripts and vowel signs). Letter-spacing pulls a
           cluster apart until the word reads as a different one, and under about 11px the marks smudge together. The
           panel's captions are set small, spaced out and in capitals for Latin, so here: no tracking anywhere, and the
           small captions get a readable size. */
        html[lang="km"] *,
        html[lang="km"] *::before,
        html[lang="km"] *::after { letter-spacing: normal !important; }
        html[lang="km"] .main-nav p,
        html[lang="km"] .cms-sidebar .main-nav .nav-subsection,
        html[lang="km"] .data-table th,
        html[lang="km"] .form-grid label,
        html[lang="km"] .sidebar-box label,
        html[lang="km"] .profile-section { font-size: 13px; }
        html[lang="km"] .eyebrow { font-size: 13px !important; }
        html[lang="km"] .badge,
        html[lang="km"] [data-dropdown] .dropdown-label,
        html[lang="km"] .form-grid label em,
        html[lang="km"] .secondary-button.compact,
        html[lang="km"] .danger-button.compact,
        html[lang="km"] .primary-button.compact { font-size: 11px; }
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
