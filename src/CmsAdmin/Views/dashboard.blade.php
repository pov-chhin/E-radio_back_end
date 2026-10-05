@extends('cms-admin::layout')

@section('title', 'Overview')

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap">
@endpush

@section('content')
    @php
        $adminUser = auth('admin')->user();

        // Avatars without a picture get a stable colour derived from the name, so a row keeps its colour between visits.
        $tones = ['primary', 'success', 'warning', 'info', 'danger', 'purple', 'teal', 'pink'];
        $toneFor = fn (?string $name) => $tones[abs(crc32((string) $name)) % count($tones)];
        $initial = fn (?string $name) => mb_strtoupper(mb_substr(trim((string) $name), 0, 1)) ?: '?';

        $channelsAwaiting = max(0, $stats['channels_total'] - $stats['channels_published']);
        $publishedShare = $stats['channels_total'] > 0 ? (int) round($stats['channels_published'] / $stats['channels_total'] * 100) : 0;
        $topPlays = max(1, (int) $topMusic->max('play_count'));
        $adminZone = config('app.admin_timezone');

        $quickActions = collect([
            ['permission' => 'create-users', 'route' => 'admin.users.create', 'icon' => 'user-plus', 'tone' => 'primary', 'title' => 'Register admin user', 'hint' => 'Invite someone to the panel'],
            ['permission' => 'create-roles', 'route' => 'admin.roles.create', 'icon' => 'shield', 'tone' => 'purple', 'title' => 'Create a role', 'hint' => 'Group permissions together'],
            ['permission' => 'create-permissions', 'route' => 'admin.permissions.create', 'icon' => 'key', 'tone' => 'teal', 'title' => 'Create a permission', 'hint' => 'Define a new capability'],
            ['permission' => 'manage-settings', 'route' => 'admin.settings.edit', 'icon' => 'cog', 'tone' => 'warning', 'title' => 'Station settings', 'hint' => 'Configure the scraper'],
        ])->filter(fn (array $action) => $adminUser->hasPermission($action['permission']));
    @endphp

    <div class="dash">
        <div class="dash-head">
            <div>
                <p class="dash-eyebrow">{{ now()->format('l, F j') }}</p>
                <h1>Welcome back, {{ explode(' ', $adminUser->name)[0] }}</h1>
                <p class="dash-sub">Here's what's happening across your admin panel today.</p>
            </div>
            @if($adminUser->hasPermission('create-radio-channels'))
                <form method="POST" action="{{ route('admin.radio-channels.sync') }}">
                    @csrf
                    <button type="submit" class="dash-btn"><i class="fa fa-refresh"></i> Sync radio channels</button>
                </form>
            @endif
        </div>

        <section class="dash-stats" aria-label="Key figures">
            <article class="dash-card dash-stat">
                <div>
                    <span class="dash-stat-label">Radio channels</span>
                    <strong class="dash-stat-value">{{ number_format($stats['channels_total']) }}</strong>
                    <small class="dash-stat-meta"><i class="fa fa-signal ok"></i><span><b>{{ number_format($stats['channels_published']) }}</b> published</span></small>
                </div>
                <span class="dash-stat-icon tone-primary"><i class="fa fa-server"></i></span>
            </article>
            <article class="dash-card dash-stat">
                <div>
                    <span class="dash-stat-label">Radio-browser sync</span>
                    <strong class="dash-stat-value">{{ $scraperSetting?->enabled ? 'Enabled' : 'Disabled' }}</strong>
                    <small class="dash-stat-meta"><i class="fa fa-globe"></i><span>Country: <b>{{ $scraperSetting?->country ?? '—' }}</b></span></small>
                </div>
                <span class="dash-stat-icon tone-info"><i class="fa fa-refresh"></i></span>
            </article>
            <article class="dash-card dash-stat">
                <div>
                    <span class="dash-stat-label">All users</span>
                    <strong class="dash-stat-value">{{ number_format($stats['users_total']) }}</strong>
                    <small class="dash-stat-meta"><i class="fa fa-users"></i>Admins and app customers</small>
                </div>
                <span class="dash-stat-icon tone-purple"><i class="fa fa-users"></i></span>
            </article>
            <article class="dash-card dash-stat">
                <div>
                    <span class="dash-stat-label">All users online</span>
                    <strong class="dash-stat-value">{{ number_format($stats['users_online']) }}</strong>
                    <small class="dash-stat-meta"><i class="fa fa-clock-o"></i>Active in the last 5 minutes</small>
                </div>
                <span class="dash-stat-icon tone-success"><i class="fa fa-bolt"></i></span>
            </article>
        </section>

        <section class="dash-row">
            <article class="dash-card">
                <header class="dash-card-head">
                    <div><h3>Recent radio channels</h3><p>Latest stations pulled from radio-browser.info</p></div>
                    @if($adminUser->hasPermission('view-radio-channels'))
                        <a class="dash-link" href="{{ route('admin.radio-channels.index') }}">View all</a>
                    @endif
                </header>
                <div class="dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr><th>Station</th><th>Status</th><th class="right dash-hide-sm">Added</th></tr>
                        </thead>
                        <tbody>
                            @forelse($recentChannels as $channel)
                                <tr>
                                    <td>
                                        <div class="dash-cell">
                                            @if($channel->cover)
                                                <img class="dash-avatar" src="{{ $channel->cover_url }}" alt="">
                                            @else
                                                <span class="dash-avatar tone-{{ $toneFor($channel->name) }}">{{ $initial($channel->name) }}</span>
                                            @endif
                                            <div class="dash-cell-copy"><b>{{ $channel->name }}</b><small>{{ $channel->bitrate ? "{$channel->bitrate} kbps" : 'Bitrate unknown' }}</small></div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($channel->is_published)
                                            <span class="dash-badge tone-success">Published</span>
                                        @else
                                            <span class="dash-badge tone-warning">Awaiting review</span>
                                        @endif
                                    </td>
                                    <td class="right muted dash-hide-sm">{{ $channel->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="dash-empty">No radio channels yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="dash-card dash-status-card">
                <header class="dash-card-head">
                    <div><h3>Channel status</h3><p>Published versus awaiting review</p></div>
                </header>
                <div class="dash-status-body">
                    <div class="dash-ring {{ $stats['channels_total'] > 0 ? '' : 'is-empty' }}" style="--p: {{ $publishedShare }}" role="img" aria-label="{{ $publishedShare }}% of radio channels are published">
                        <span><b>{{ $publishedShare }}%</b><small>published</small></span>
                    </div>
                    <ul class="dash-legend">
                        <li><i class="tone-success"></i>Published<b>{{ number_format($stats['channels_published']) }}</b></li>
                        <li><i class="tone-warning"></i>Awaiting review<b>{{ number_format($channelsAwaiting) }}</b></li>
                        <li><i class="tone-primary"></i>Total channels<b>{{ number_format($stats['channels_total']) }}</b></li>
                    </ul>
                </div>
            </article>
        </section>

        <section class="dash-trio">
            <article class="dash-card">
                <header class="dash-card-head">
                    <div><h3>Most played music</h3><p>Top 10 songs by play count</p></div>
                    @if($adminUser->hasPermission('view-media-content'))
                        <a class="dash-link" href="{{ route('admin.music.index', ['category' => 'song']) }}">View all</a>
                    @endif
                </header>
                <div class="dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr><th>Track</th><th class="right">Plays</th></tr>
                        </thead>
                        <tbody>
                            @forelse($topMusic as $track)
                                <tr>
                                    <td>
                                        <div class="dash-cell">
                                            @if($track->thumbnail_url)
                                                <img class="dash-avatar" src="{{ $track->thumbnail_url }}" alt="">
                                            @else
                                                <span class="dash-avatar tone-{{ $loop->iteration <= 3 ? 'primary' : 'slate' }}">{{ $loop->iteration }}</span>
                                            @endif
                                            <div class="dash-cell-copy"><b>{{ $track->title }}</b><small>{{ $track->artist?->stage_name ?: ($track->artist?->name ?? 'Unknown artist') }}</small></div>
                                        </div>
                                    </td>
                                    <td class="right">
                                        <div class="dash-plays"><b>{{ number_format($track->play_count) }}</b><span><i style="width: {{ $track->play_count > 0 ? max(4, (int) round($track->play_count / $topPlays * 100)) : 0 }}%"></i></span></div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="dash-empty">No songs played yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="dash-card dash-geo">
                <header class="dash-card-head">
                    <div><h3>Visitors by country</h3><p>{{ number_format($locatedUsers) }} of {{ number_format($stats['users_total']) }} users have a known country</p></div>
                </header>
                @if($countries->isEmpty())
                    <div class="dash-geo-empty">
                        <span class="dash-avatar tone-info"><i class="fa fa-globe"></i></span>
                        <b>No location data yet</b>
                        <p>A user's country is saved from the country header your CDN or proxy sends (for example Cloudflare's <code>CF-IPCountry</code>) the next time they use the app.</p>
                    </div>
                @else
                    <div class="dash-geo-map" data-country-map data-countries="{{ $countries->toJson() }}"></div>
                    <ul class="dash-geo-list">
                        @foreach($countries->take(5) as $code => $total)
                            <li>
                                <span class="dash-geo-name" data-country-code="{{ $code }}">{{ $code }}</span>
                                <span class="dash-geo-bar"><i style="width: {{ max(3, (int) round($total / $locatedUsers * 100)) }}%"></i></span>
                                <b>{{ number_format($total) }}</b>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </article>

            <article class="dash-card">
                <header class="dash-card-head">
                    <div><h3>Users online</h3><p>Active in the last 5 minutes</p></div>
                    @if($adminUser->hasPermission('view-customers'))
                        <a class="dash-link" href="{{ route('admin.customers.index') }}">Customers</a>
                    @endif
                </header>
                <ul class="dash-people">
                    @forelse($onlineUsers as $person)
                        <li>
                            @if($person->avatar_url)
                                <img class="dash-avatar" src="{{ $person->avatar_url }}" alt="">
                            @else
                                <span class="dash-avatar tone-{{ $toneFor($person->display_name) }}">{{ $initial($person->display_name) }}</span>
                            @endif
                            <div class="dash-cell-copy"><b>{{ $person->display_name }}</b><small>{{ $person->email }}</small></div>
                            <div class="dash-people-meta">
                                <span class="dash-badge {{ $person->type === 'admin' ? 'tone-primary' : 'tone-purple' }}">{{ $person->type === 'admin' ? 'Admin' : 'Customer' }}</span>
                                <small>{{ $person->last_active->gt(now()->subMinute()) ? 'Just now' : $person->last_active->diffForHumans(['short' => true]) }}</small>
                            </div>
                        </li>
                    @empty
                        <li class="dash-empty">Nobody is online right now.</li>
                    @endforelse
                </ul>
                @if($stats['users_online'] > $onlineUsers->count())
                    <p class="dash-card-foot">Showing {{ $onlineUsers->count() }} of {{ number_format($stats['users_online']) }} people online</p>
                @endif
            </article>
        </section>

        <section class="dash-card dash-block">
            <header class="dash-card-head">
                <div><h3>Recent podcasts</h3><p>Newest shows added to the catalog</p></div>
                @if($adminUser->hasPermission('view-programs'))
                    <a class="dash-btn dash-btn-sm" href="{{ route('admin.programs.index') }}"><i class="fa fa-podcast"></i> View all</a>
                @endif
            </header>
            <div class="dash-table-wrap">
                <table class="dash-table">
                    <thead>
                        <tr><th>Podcast</th><th class="dash-hide-sm">Added</th><th>Status</th><th class="right">Episodes</th><th class="right dash-hide-sm">Plays</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recentPodcasts as $podcast)
                            @php $addedAt = $podcast->created_at->copy()->setTimezone($adminZone); @endphp
                            <tr>
                                <td>
                                    <div class="dash-cell">
                                        @if($podcast->cover_image_url)
                                            <img class="dash-avatar" src="{{ $podcast->cover_image_url }}" alt="">
                                        @else
                                            <span class="dash-avatar tone-{{ $toneFor($podcast->name) }}"><i class="fa fa-podcast"></i></span>
                                        @endif
                                        <div class="dash-cell-copy"><b>{{ $podcast->name }}</b><small>{{ $podcast->type?->name ?? 'No type' }}</small></div>
                                    </div>
                                </td>
                                <td class="dash-hide-sm">
                                    <div class="dash-cell-copy"><small>{{ $addedAt->format('h:i A') }}</small><b>{{ $addedAt->format('F j, Y') }}</b></div>
                                </td>
                                <td><span class="dash-badge {{ $podcast->is_published ? 'tone-success' : 'tone-warning' }}">{{ $podcast->is_published ? 'Published' : 'Draft' }}</span></td>
                                <td class="right"><b class="dash-num">{{ number_format($podcast->media_contents_count) }}</b></td>
                                <td class="right dash-hide-sm"><b class="dash-num">{{ number_format((int) $podcast->media_contents_sum_play_count) }}</b></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="dash-empty">No podcasts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($quickActions->isNotEmpty())
            <section class="dash-card dash-block">
                <header class="dash-card-head">
                    <div><h3>Quick actions</h3><p>Jump straight to a common task</p></div>
                </header>
                <div class="dash-actions">
                    @foreach($quickActions as $action)
                        <a href="{{ route($action['route']) }}">
                            <span class="dash-avatar tone-{{ $action['tone'] }}"><i class="fa fa-{{ $action['icon'] }}"></i></span>
                            <span class="dash-cell-copy"><b>{{ $action['title'] }}</b><small>{{ $action['hint'] }}</small></span>
                            <i class="fa fa-angle-right dash-chevron"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
