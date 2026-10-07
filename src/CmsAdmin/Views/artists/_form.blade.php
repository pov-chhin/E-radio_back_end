@csrf

<label class="toggle-setting">
    <span><b>{{ __('Active') }}</b><small>{{ __("Shows an active badge on the artist's profile.") }}</small></span>
    <span class="switch"><input type="checkbox" name="verified" value="1" @checked(old('verified', $artist->verified ?? false))><i></i></span>
</label>

<div class="form-grid" style="margin-bottom:24px">
    <label>{{ __('Profile') }} <small class="muted" style="font-weight:400">{{ __('— cropped to WebP, 300×300') }}</small>
        @if(isset($artist) && $artist->photo)
            <img src="{{ $artist->photo_url }}" alt="" style="width:60px;height:60px;border-radius:8px;object-fit:cover">
            <span class="check-row" style="margin:0"><input type="checkbox" name="remove_photo" value="1"> {{ __('Remove current photo') }}</span>
        @endif
        <div class="tag-view-toggle" style="align-self:flex-start">
            <button type="button" class="{{ old('photo_url') ? '' : 'active' }}" data-image-mode-tab="upload" data-image-field="photo">{{ __('Upload') }}</button>
            <button type="button" class="{{ old('photo_url') ? 'active' : '' }}" data-image-mode-tab="url" data-image-field="photo">{{ __('Paste URL') }}</button>
        </div>
        <input id="photo" name="photo" type="file" accept="image/*" data-image-mode-panel="upload" data-image-field="photo" @if(old('photo_url')) hidden disabled @endif>
        <input name="photo_url" type="text" placeholder="https://…" value="{{ old('photo_url') }}" data-image-mode-panel="url" data-image-field="photo" @unless(old('photo_url')) hidden disabled @endunless>
        @error('photo') <em>{{ $message }}</em> @enderror
        @error('photo_url') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Cover') }} <small class="muted" style="font-weight:400">{{ __('— cropped to WebP, 1200×400') }}</small>
        @if(isset($artist) && $artist->cover)
            <img src="{{ $artist->cover_url }}" alt="" style="width:130px;height:43px;border-radius:8px;object-fit:cover">
            <span class="check-row" style="margin:0"><input type="checkbox" name="remove_cover" value="1"> {{ __('Remove current cover') }}</span>
        @endif
        <div class="tag-view-toggle" style="align-self:flex-start">
            <button type="button" class="{{ old('cover_url') ? '' : 'active' }}" data-image-mode-tab="upload" data-image-field="cover">{{ __('Upload') }}</button>
            <button type="button" class="{{ old('cover_url') ? 'active' : '' }}" data-image-mode-tab="url" data-image-field="cover">{{ __('Paste URL') }}</button>
        </div>
        <input id="cover" name="cover" type="file" accept="image/*" data-image-mode-panel="upload" data-image-field="cover" @if(old('cover_url')) hidden disabled @endif>
        <input name="cover_url" type="text" placeholder="https://…" value="{{ old('cover_url') }}" data-image-mode-panel="url" data-image-field="cover" @unless(old('cover_url')) hidden disabled @endunless>
        @error('cover') <em>{{ $message }}</em> @enderror
        @error('cover_url') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Name') }}
        <input id="name" name="name" type="text" value="{{ old('name', $artist->name ?? '') }}" required>
        @error('name') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Slug') }}
        <input id="slug" name="slug" type="text" value="{{ old('slug', $artist->slug ?? '') }}" required>
        @error('slug') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Stage name') }}
        <input id="stage_name" name="stage_name" type="text" value="{{ old('stage_name', $artist->stage_name ?? '') }}">
        @error('stage_name') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Status') }}
        <select id="status" name="status" required>
            @foreach($statuses as $value)
                <option value="{{ $value }}" @selected(old('status', $artist->status ?? 'active') === $value)>{{ __(ucfirst($value)) }}</option>
            @endforeach
        </select>
        @error('status') <em>{{ $message }}</em> @enderror
    </label>
    <label class="full">{{ __('Bio') }}
        <textarea id="bio" name="bio">{{ old('bio', $artist->bio ?? '') }}</textarea>
        @error('bio') <em>{{ $message }}</em> @enderror
    </label>
</div>

<div style="margin-bottom:24px">
    <label style="display:block;font-size:9px;font-weight:700;margin-bottom:10px">{{ __('Social links') }}</label>
    @php $socialLinks = old('social_links', $artist->social_links ?? []); @endphp
    <div class="form-grid">
        @foreach($socialPlatforms as $platform)
            <label>{{ ucfirst($platform) }}
                <input name="social_links[{{ $platform }}]" type="text" value="{{ $socialLinks[$platform] ?? '' }}" placeholder="https://…">
                @error('social_links.'.$platform) <em>{{ $message }}</em> @enderror
            </label>
        @endforeach
    </div>
</div>

<button type="submit" class="primary-button">{{ __('Save') }}</button>
