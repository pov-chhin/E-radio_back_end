@extends('cms-admin::layout')

@section('title', 'Overview')

@section('content')
    @php $adminUser = auth('admin')->user(); @endphp

    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ now()->format('l, F j') }}</p>
            <h1>Welcome back, {{ explode(' ', $adminUser->name)[0] }}</h1>
            <p>Here's what's happening across your admin panel today.</p>
        </div>
        @if($adminUser->hasPermission('create-radio-channels'))
            <form method="POST" action="{{ route('admin.radio-channels.sync') }}">
                @csrf
                <button type="submit" class="primary-button">Sync radio channels</button>
            </form>
        @endif
    </div>

    <section class="stat-grid">
        <article class="stat-card">
            <div class="stat-icon pink fa fa-server"></div>
            <span>Radio channels</span>
            <strong>{{ number_format($stats['channels_total']) }}</strong>
            <small class="trend up">{{ $stats['channels_published'] }} published</small>
        </article>
        <article class="stat-card">
            <div class="stat-icon amber fa fa-cog"></div>
            <span>Radio-browser sync</span>
            <strong>{{ $scraperSetting?->enabled ? 'Enabled' : 'Disabled' }}</strong>
            <small class="trend neutral">{{ $scraperSetting?->country ?? '—' }}</small>
        </article>
        <article class="stat-card">
            <div class="stat-icon violet fa fa-address-book"></div>
            <span>All users</span>
            <strong>{{ number_format($stats['users_total']) }}</strong>
            <small class="trend neutral">Admins and app customers</small>
        </article>
        <article class="stat-card">
            <div class="stat-icon green fa fa-circle"></div>
            <span>All users online</span>
            <strong>{{ number_format($stats['users_online']) }}</strong>
            <small class="trend neutral">Active in the last 5 minutes</small>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="panel activity-panel">
            <div class="panel-head">
                <div><h3>Recent radio channels</h3><p>Latest stations pulled from radio-browser.info</p></div>
                @if($adminUser->hasPermission('view-radio-channels'))
                    <a class="text-button" href="{{ route('admin.radio-channels.index') }}">View all</a>
                @endif
            </div>
            <div class="activity-list">
                @forelse($recentChannels as $channel)
                    <div class="activity-item">
                        <span class="activity-dot {{ $channel->is_published ? '' : 'amber' }}"></span>
                        <div><b>{{ $channel->name }}</b><small>{{ $channel->is_published ? 'Published' : 'Awaiting review' }} · {{ $channel->created_at->diffForHumans() }}</small></div>
                    </div>
                @empty
                    <p class="empty-state">No radio channels yet.</p>
                @endforelse
            </div>
        </article>
        <article class="panel activity-panel">
            <div class="panel-head">
                <div><h3>Recent podcasts</h3><p>Newest shows added to the catalog</p></div>
                @if($adminUser->hasPermission('view-programs'))
                    <a class="text-button" href="{{ route('admin.programs.index') }}">View all</a>
                @endif
            </div>
            <div class="activity-list">
                @forelse($recentPodcasts as $podcast)
                    <div class="activity-item">
                        <span class="activity-dot {{ $podcast->is_published ? '' : 'amber' }}"></span>
                        <div><b>{{ $podcast->name }}</b><small>{{ $podcast->type?->name ?? 'No type' }} · {{ $podcast->created_at->diffForHumans() }}</small></div>
                    </div>
                @empty
                    <p class="empty-state">No podcasts yet.</p>
                @endforelse
            </div>
        </article>
        <article class="panel activity-panel">
            <div class="panel-head">
                <div><h3>Most played music</h3><p>Top 10 songs by play count</p></div>
                @if($adminUser->hasPermission('view-media-content'))
                    <a class="text-button" href="{{ route('admin.music.index', ['category' => 'song']) }}">View all</a>
                @endif
            </div>
            <div class="activity-list">
                @forelse($topMusic as $track)
                    <div class="activity-item">
                        <span class="activity-dot blue"></span>
                        <div><b>{{ $track->title }}</b><small>{{ $track->artist?->stage_name ?: ($track->artist?->name ?? 'Unknown artist') }} · {{ number_format($track->play_count) }} plays</small></div>
                    </div>
                @empty
                    <p class="empty-state">No songs played yet.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="panel upcoming-panel">
        <div class="panel-head"><div><h3>Quick actions</h3><p>Jump straight to a common task</p></div></div>
        <div class="quick-actions" style="margin-top:15px">
            @if($adminUser->hasPermission('create-users'))
                <a href="{{ route('admin.users.create') }}"><span class="fa fa-users"></span><b>Register admin user</b><small>Invite someone to the panel</small></a>
            @endif
            @if($adminUser->hasPermission('create-roles'))
                <a href="{{ route('admin.roles.create') }}"><span class="fa fa-shield"></span><b>Create a role</b><small>Group permissions together</small></a>
            @endif
            @if($adminUser->hasPermission('create-permissions'))
                <a href="{{ route('admin.permissions.create') }}"><span class="fa fa-key"></span><b>Create a permission</b><small>Define a new capability</small></a>
            @endif
            @if($adminUser->hasPermission('manage-settings'))
                <a href="{{ route('admin.settings.edit') }}"><span class="fa fa-cog"></span><b>Station settings</b><small>Configure the scraper</small></a>
            @endif
        </div>
    </section>
@endsection
