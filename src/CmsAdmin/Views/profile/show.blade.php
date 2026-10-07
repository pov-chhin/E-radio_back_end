@extends('cms-admin::layout')

@section('title', 'My Profile')

@section('content')
    @php
        $language = \Src\Setting\Models\LanguageSetting::LOCALES[$user->locale] ?? null;
    @endphp

    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Account') }}</p>
            <h1>{{ __('My Profile') }}</h1>
            <p>{{ __('Your account details.') }}</p>
        </div>
    </div>

    <div class="panel profile-card">
        <div class="profile-head">
            <span class="profile-avatar" aria-hidden="true">
                @if($user->photo_url)
                    <img src="{{ $user->photo_url }}" alt="">
                @else
                    {{ strtoupper(mb_substr($user->name, 0, 2)) }}
                @endif
            </span>

            <div class="profile-identity">
                <h2>{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
                @forelse($user->roles as $role)
                    <span class="badge slate">{{ __($role->name) }}</span>
                @empty
                    <span class="muted">{{ __('None') }}</span>
                @endforelse
            </div>

            <div class="profile-actions">
                <a href="{{ route('admin.profile.edit') }}" class="primary-button compact"><i class="fa fa-pencil"></i> {{ __('Edit Profile') }}</a>
            </div>
        </div>

        <dl class="profile-rows">
            <div><dt>{{ __('Name') }}</dt><dd>{{ $user->name }}</dd></div>
            <div><dt>{{ __('First name') }}</dt><dd>{{ $user->first_name ?: '—' }}</dd></div>
            <div><dt>{{ __('Last name') }}</dt><dd>{{ $user->last_name ?: '—' }}</dd></div>
            <div><dt>{{ __('Email') }}</dt><dd>{{ $user->email }}</dd></div>
            <div><dt>{{ __('Phone number') }}</dt><dd>{{ $user->phone_number ?: '—' }}</dd></div>
            <div><dt>{{ __('Address') }}</dt><dd>{{ $user->address ?: '—' }}</dd></div>
            <div>
                <dt>{{ __('Roles') }}</dt>
                <dd>
                    @forelse($user->roles as $role)
                        <span class="badge slate">{{ __($role->name) }}</span>
                    @empty
                        <span class="muted">{{ __('None') }}</span>
                    @endforelse
                </dd>
            </div>
            <div><dt>{{ __('Language') }}</dt><dd>{{ $language ? $language['name'].' ('.$language['short'].')' : __('Site default') }}</dd></div>
            <div>
                <dt>{{ __('Status') }}</dt>
                <dd>
                    @if($user->is_active)
                        <span class="badge green">{{ __('Active') }}</span>
                    @else
                        <span class="badge slate">{{ __('Inactive') }}</span>
                    @endif
                </dd>
            </div>
            <div><dt>{{ __('Member since') }}</dt><dd>{{ $user->created_at?->translatedFormat('F j, Y') ?? '—' }}</dd></div>
        </dl>
    </div>
@endsection
