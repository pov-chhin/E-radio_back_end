{{-- Shared page heading --}}
<div class="page-heading">
    <div><p class="eyebrow">{{ $eyebrow ?? 'Broadcast control' }}</p><h1>{{ $title }}</h1><p>{{ $subtitle }}</p></div>
    <div class="heading-actions">{{ $slot ?? '' }}</div>
</div>
