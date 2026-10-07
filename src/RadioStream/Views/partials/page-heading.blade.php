{{-- Shared page heading --}}
<div class="page-heading">
    <div><p class="eyebrow">{{ __($eyebrow ?? 'Broadcast control') }}</p><h1>{{ __($title) }}</h1><p>{{ __($subtitle) }}</p></div>
    <div class="heading-actions">{{ $slot ?? '' }}</div>
</div>
