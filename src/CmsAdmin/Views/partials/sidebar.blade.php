<aside class="sidebar cms-sidebar" id="sidebar">
    <a class="brand" href="{{ route('admin.dashboard') }}" aria-label="Admin home">
        <span class="brand-mark"><i></i><i></i><i></i><i></i></span>
        <span>cms admin</span>
    </a>
    <div class="station-switcher">
        <span class="station-avatar">{{ strtoupper(substr(config('app.name'), 0, 2)) }}</span>
        <span><b>{{ config('app.name') }}</b><small>Admin panel</small></span>
    </div>
    @php
        $adminNav = [
            ['route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'label' => 'Overview', 'icon' => 'th-large', 'permission' => null],
            ['route' => 'admin.radio-channels.index', 'pattern' => 'admin.radio-channels.*', 'label' => 'Radio Channels', 'icon' => 'server', 'permission' => 'view-radio-channels'],
            ['route' => 'admin.programs.index', 'pattern' => 'admin.programs.*', 'label' => 'Podcasts', 'icon' => 'podcast', 'permission' => 'view-programs', 'section' => 'Catalog',
                'active' => fn () => request()->routeIs('admin.programs.*') || (request()->routeIs('admin.media-contents.*') && request()->filled('podcast'))],
            ['route' => 'admin.music.index', 'pattern' => 'admin.music.*', 'label' => 'Article', 'icon' => 'newspaper-o', 'permission' => 'view-media-content',
                'active' => fn () => request()->routeIs('admin.music.*') || (request()->routeIs('admin.media-contents.*') && ! request()->filled('podcast'))],
            ['route' => 'admin.categories.index', 'pattern' => 'admin.categories.*', 'label' => 'Categories', 'icon' => 'sitemap', 'permission' => 'view-categories', 'section' => 'Catalog'],
            ['route' => 'admin.tags.index', 'pattern' => 'admin.tags.*', 'label' => 'Tags', 'icon' => 'tags', 'permission' => 'view-tags'],
            ['route' => 'admin.artists.index', 'pattern' => 'admin.artists.*', 'label' => 'Artists', 'icon' => 'microphone', 'permission' => 'view-artists'],
            ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Admin Users', 'icon' => 'users', 'permission' => 'view-users', 'section' => 'Access control'],
            ['route' => 'admin.customers.index', 'pattern' => 'admin.customers.*', 'label' => 'Customers', 'icon' => 'user', 'permission' => 'view-customers'],
            ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Roles', 'icon' => 'shield', 'permission' => 'view-roles'],
            ['route' => 'admin.permissions.index', 'pattern' => 'admin.permissions.*', 'label' => 'Permissions', 'icon' => 'key', 'permission' => 'view-permissions'],
            ['route' => 'admin.settings.edit', 'pattern' => 'admin.settings.*', 'label' => 'Settings', 'icon' => 'cog', 'permission' => 'manage-settings'],
        ];
    @endphp
    <nav class="main-nav" aria-label="Main navigation">
        <p>Workspace</p>
        @php $currentSection = null; @endphp
        @foreach($adminNav as $item)
            @continue($item['permission'] && ! $admin?->hasPermission($item['permission']))
            @if(($item['section'] ?? null) && $item['section'] !== $currentSection)
                <p class="nav-section">{{ $item['section'] }}</p>
                @php $currentSection = $item['section']; @endphp
            @endif
            @if(isset($item['children']))
                <details class="nav-group" @if(request()->routeIs($item['pattern'])) open @endif>
                    <summary @class(['active' => request()->routeIs($item['pattern'])])>
                        <span class="nav-icon fa fa-{{ $item['icon'] }}"></span>
                        <span>{{ $item['label'] }}</span>
                        <span class="fa fa-chevron-down nav-group-chevron"></span>
                    </summary>
                    <div class="nav-submenu">
                        @foreach($item['children'] as $child)
                            @if(isset($child['section']))<p class="nav-subsection">{{ $child['section'] }}</p>@endif
                            <a href="{{ route($child['route']) }}" @class(['active' => request()->routeIs($child['pattern'])])>
                                <span class="nav-icon fa fa-{{ $child['icon'] }}"></span><span>{{ $child['label'] }}</span>
                                @if(isset($child['badge']))<em>{{ $child['badge'] }}</em>@endif
                            </a>
                        @endforeach
                    </div>
                </details>
            @else
                <a href="{{ route($item['route']) }}" @class(['active' => isset($item['active']) ? $item['active']() : request()->routeIs($item['pattern'])])>
                    <span class="nav-icon fa fa-{{ $item['icon'] }}"></span><span>{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </nav>
</aside>
