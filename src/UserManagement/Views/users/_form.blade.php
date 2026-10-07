@csrf

@php
    $editing = isset($user);
    // Editing yourself leaves your roles alone (the controller ignores them), so they are shown but locked.
    $ownAccount = $editing && $user->id === auth('admin')->id();
    $checkedRoles = collect(old('roles', $editing ? $user->roles->pluck('id')->all() : []));
@endphp

<div class="form-grid" style="margin-bottom:24px">
    <label>{{ __('Name') }}
        <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required>
        @error('name') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Email') }}
        <input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required>
        @error('email') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Password') }}
        <input id="password" name="password" type="password" autocomplete="new-password" @required(! $editing)>
        @if($editing)
            <span class="muted" style="font-size:9px;font-weight:500">{{ __('Leave blank to keep the current password.') }}</span>
        @endif
        @error('password') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Language') }}
        <select id="locale" name="locale">
            <option value="">{{ __('Site default') }}</option>
            @foreach(\Src\Setting\Models\LanguageSetting::LOCALES as $code => $language)
                <option value="{{ $code }}" @selected(old('locale', $user->locale ?? null) === $code)>{{ $language['name'] }} ({{ $language['short'] }})</option>
            @endforeach
        </select>
        @error('locale') <em>{{ $message }}</em> @enderror
    </label>
</div>

<div style="margin-bottom:24px">
    <label style="display:block;font-size:9px;font-weight:700;margin-bottom:10px">{{ __('Roles') }}</label>
    <div class="checkbox-grid">
        @forelse($roles as $role)
            <label>
                <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($checkedRoles->contains($role->id)) @disabled($ownAccount)>
                {{ __($role->name) }}
            </label>
        @empty
            <p class="muted">{!! __('No roles yet — <a href=":url">create one</a>.', ['url' => route('admin.roles.create')]) !!}</p>
        @endforelse
    </div>
    @if($ownAccount)
        <p class="muted" style="font-size:10px;margin:10px 0 0">{{ __("You can't change your own roles.") }}</p>
    @endif
</div>

<button type="submit" class="primary-button">{{ $submitLabel }}</button>
