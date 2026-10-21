@csrf

<div class="form-grid" style="margin-bottom:24px">
    <label class="full">{{ __('Profile') }} <small class="muted" style="font-weight:400">{{ __('— cropped to WebP, 300×300') }}</small>
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
    <label>{{ __('Name') }}
        <input id="name" name="name" type="text" value="{{ old('name', $artist->name ?? '') }}" required>
        @error('name') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Region') }}
        <select id="region" name="region">
            <option value="">{{ __('Select :name', ['name' => __('Region')]) }}</option>
            @foreach($regions as $value)
                <option value="{{ $value }}" @selected(old('region', $artist->region ?? '') === $value)>{{ __(ucfirst($value)) }}</option>
            @endforeach
        </select>
        @error('region') <em>{{ $message }}</em> @enderror
    </label>
</div>

@php
    $bio = (string) old('bio', $artist->bio ?? '');
    // The editor works in HTML. A bio written before it was plain text, so each of its lines becomes a paragraph here instead of running together.
    if ($bio !== '' && $bio === strip_tags($bio)) {
        $bio = collect(preg_split('/\R/', $bio))->map(fn ($line) => '<p>'.e($line).'</p>')->implode('');
    }
@endphp
{{-- The same editor as a Description: the box below is the one that is sent, and the script fills it from the editor. --}}
<div class="editor-block" style="margin-bottom:24px">
    <h4>{{ __('Bio') }}</h4>
    <div class="rich-editor" data-rich-text></div>
    <textarea id="bio" name="bio" data-rich-text-source hidden>{{ $bio }}</textarea>
    @error('bio') <em class="field-error">{{ $message }}</em> @enderror
</div>

<button type="submit" class="primary-button">{{ __('Save') }}</button>
