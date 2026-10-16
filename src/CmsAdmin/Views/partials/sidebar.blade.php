<aside class="sidebar cms-sidebar" id="sidebar">
    <a class="brand" href="{{ route('admin.dashboard') }}" aria-label="{{ __('Admin home') }}">
        <span class="brand-mark"><i></i><i></i><i></i><i></i></span>
        <span>cms admin</span>
    </a>
    <div class="station-switcher">
        <span class="station-avatar station-brand-logo">
            <img src="{{ asset('images/e-radio-ams-logo.png') }}" alt="" aria-hidden="true">
        </span>
        <span><b>{{ config('app.name') }}</b><small>{{ __('Admin panel') }}</small></span>
    </div>
    @php
        $adminNav = [
            ['route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'label' => 'Overview', 'icon' => 'th-large', 'permission' => null],
            ['route' => 'admin.radio-channels.index', 'pattern' => 'admin.radio-channels.*', 'label' => 'Radio Channels', 'icon' => 'server', 'permission' => 'view-radio-channels'],
            // The radio studio lives on its own site: a link out (new tab), shown to whoever sees Radio Channels. Its
            // address is edited under Settings → Radio Studio; with none set there is no link.
            ['url' => \Src\Setting\Models\RadioStudioSetting::studioUrl(), 'label' => 'Radio Studio', 'icon' => 'headphones', 'permission' => 'view-radio-channels'],
            ['route' => 'admin.programs.index', 'pattern' => 'admin.programs.*', 'label' => 'Podcasts', 'icon' => 'podcast', 'permission' => 'view-programs', 'section' => 'Taxonomy',
                'active' => fn () => request()->routeIs('admin.programs.*') || (request()->routeIs('admin.media-contents.*') && request()->filled('podcast'))],
            ['route' => 'admin.music.index', 'pattern' => 'admin.music.*', 'label' => 'Article', 'icon' => 'newspaper-o', 'permission' => 'view-media-content',
                'active' => fn () => request()->routeIs('admin.music.*') || (request()->routeIs('admin.media-contents.*') && ! request()->filled('podcast'))],
            ['route' => 'admin.categories.index', 'pattern' => 'admin.categories.*', 'label' => 'Categories', 'icon' => 'sitemap', 'permission' => 'view-categories', 'section' => 'Taxonomy'],
            ['route' => 'admin.tags.index', 'pattern' => 'admin.tags.*', 'label' => 'Tags', 'icon' => 'tags', 'permission' => 'view-tags'],
            ['route' => 'admin.artists.index', 'pattern' => 'admin.artists.*', 'label' => 'Artists', 'icon' => 'microphone', 'permission' => 'view-artists'],
            ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Admin Users', 'icon' => 'users', 'permission' => 'view-users', 'section' => 'Access control'],
            ['route' => 'admin.customers.index', 'pattern' => 'admin.customers.*', 'label' => 'Customers', 'icon' => 'user', 'permission' => 'view-customers'],
            ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Roles', 'icon' => 'shield', 'permission' => 'view-roles'],
            // Permissions are not in the menu: they come from the routes (permissions:sync). The page still opens at
            // /admin/permissions for whoever holds view-permissions.
            ['route' => 'admin.settings.edit', 'pattern' => 'admin.settings.*', 'label' => 'Settings', 'icon' => 'cog', 'permission' => 'manage-settings'],
        ];
    @endphp
    <nav class="main-nav" aria-label="{{ __('Main navigation') }}">
        <p>{{ __('Workspace') }}</p>
        @php $currentSection = null; @endphp
        @foreach($adminNav as $item)
            @continue($item['permission'] && ! $admin?->hasPermission($item['permission']))
            @continue(array_key_exists('url', $item) && blank($item['url']))
            @if(($item['section'] ?? null) && $item['section'] !== $currentSection)
                <p class="nav-section">{{ __($item['section']) }}</p>
                @php $currentSection = $item['section']; @endphp
            @endif
            @if(isset($item['children']))
                <details class="nav-group" @if(request()->routeIs($item['pattern'])) open @endif>
                    <summary @class(['active' => request()->routeIs($item['pattern'])])>
                        <span class="nav-icon fa fa-{{ $item['icon'] }}"></span>
                        <span>{{ __($item['label']) }}</span>
                        <span class="fa fa-chevron-down nav-group-chevron"></span>
                    </summary>
                    <div class="nav-submenu">
                        @foreach($item['children'] as $child)
                            @if(isset($child['section']))<p class="nav-subsection">{{ __($child['section']) }}</p>@endif
                            <a href="{{ route($child['route']) }}" @class(['active' => request()->routeIs($child['pattern'])])>
                                <span class="nav-icon fa fa-{{ $child['icon'] }}"></span><span>{{ __($child['label']) }}</span>
                                @if(isset($child['badge']))<em>{{ $child['badge'] }}</em>@endif
                            </a>
                        @endforeach
                    </div>
                </details>
            @elseif(isset($item['url']))
                {{-- An address outside this panel: opens in a new tab and is never the current page. --}}
                <a href="{{ $item['url'] }}" target="_blank" rel="noopener">
                    <span class="nav-icon fa fa-{{ $item['icon'] }}"></span><span>{{ __($item['label']) }}</span>
                    <span class="fa fa-external-link" aria-hidden="true" style="margin-left:auto;font-size:10px;opacity:.55"></span>
                </a>
            @else
                <a href="{{ route($item['route']) }}" @class(['active' => isset($item['active']) ? $item['active']() : request()->routeIs($item['pattern'])])>
                    <span class="nav-icon fa fa-{{ $item['icon'] }}"></span><span>{{ __($item['label']) }}</span>
                </a>
            @endif
        @endforeach
    </nav>
</aside>
