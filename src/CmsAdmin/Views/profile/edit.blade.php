@extends('cms-admin::layout')

@section('title', 'Edit Profile')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Account') }}</p>
            <h1>{{ __('Edit Profile') }}</h1>
            <p>{{ __('Your name, email, language, and password.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.profile.update') }}" class="panel form-card" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="profile-photo-field">
            <span class="profile-avatar" id="photo-preview" aria-hidden="true">
                @if($user->photo_url)
                    <img src="{{ $user->photo_url }}" alt="">
                @else
                    {{ strtoupper(mb_substr($user->name, 0, 2)) }}
                @endif
            </span>
            <div>
                <label for="photo">{{ __('Photo') }} <small class="muted" style="font-weight:400">{{ __('— cropped to a square WebP') }}</small></label>
                <input id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                @error('photo') <em>{{ $message }}</em> @enderror
                @if($user->photo_url)
                    <span class="check-row" style="margin:8px 0 0"><input type="checkbox" name="remove_photo" value="1"> {{ __('Remove current photo') }}</span>
                @endif
            </div>
        </div>

        <div class="form-grid" style="margin-bottom:28px">
            <label>{{ __('Name') }}
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" autocomplete="name" required>
                @error('name') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Email') }}
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" required>
                @error('email') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('First name') }}
                <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $user->first_name) }}" autocomplete="given-name">
                @error('first_name') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Last name') }}
                <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $user->last_name) }}" autocomplete="family-name">
                @error('last_name') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Phone number') }}
                <input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number', $user->phone_number) }}" autocomplete="tel">
                @error('phone_number') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Language') }}
                <select id="locale" name="locale">
                    <option value="">{{ __('Site default') }}</option>
                    @foreach(\Src\Setting\Models\LanguageSetting::LOCALES as $code => $language)
                        <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $language['name'] }} ({{ $language['short'] }})</option>
                    @endforeach
                </select>
                @error('locale') <em>{{ $message }}</em> @enderror
            </label>
            <label class="full">{{ __('Address') }}
                <input id="address" name="address" type="text" value="{{ old('address', $user->address) }}" autocomplete="street-address">
                @error('address') <em>{{ $message }}</em> @enderror
            </label>
        </div>

        <p class="profile-section" id="change-password">{{ __('Change password') }}</p>
        <p class="muted" style="font-size:10px;margin:-6px 0 14px">{{ __('Leave the password fields blank to keep your current password.') }}</p>

        <div class="form-grid" style="margin-bottom:24px">
            <label class="full">{{ __('Current password') }}
                <input id="current_password" name="current_password" type="password" autocomplete="current-password">
                @error('current_password') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('New password') }}
                <input id="password" name="password" type="password" autocomplete="new-password">
                @error('password') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Confirm new password') }}
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            </label>
        </div>

        <div style="display:flex;gap:10px">
            <button type="submit" class="primary-button">{{ __('Save changes') }}</button>
            <a href="{{ route('admin.profile.show') }}" class="secondary-button">{{ __('Cancel') }}</a>
        </div>
    </form>
    <script>
        // Show the chosen picture in the round frame before it is saved.
        document.getElementById('photo')?.addEventListener('change', function () {
            var file = this.files[0], frame = document.getElementById('photo-preview');
            if (!file || !frame) return;
            var img = frame.querySelector('img') || frame.appendChild(document.createElement('img'));
            img.src = URL.createObjectURL(file);
            img.alt = '';
        });
    </script>
@endsection
