@csrf

<label class="toggle-setting">
    <span><b>{{ __('Published') }}</b><small>{{ __('Listed in the app once this is on and the podcast has at least one public episode.') }}</small></span>
    <span class="switch"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $program->is_published ?? false))><i></i></span>
</label>

<div class="form-grid" style="margin-bottom:24px">
    <label>{{ __('Podcast type') }}
        {{-- The tags under Podcast: Health, Economy/Finance, Education, Politic, Civilization. --}}
        <select id="tag_id" name="tag_id" @required(! isset($program))>
            <option value="">{{ isset($program) ? __('— No type —') : __('Select a podcast type…') }}</option>
            @foreach($types as $type)
                <option value="{{ $type->id }}" @selected((int) old('tag_id', $program->tag_id ?? $defaultTagId ?? '') === $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        @error('tag_id') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Name') }}
        <input id="name" name="name" type="text" value="{{ old('name', $program->name ?? '') }}" required>
        @error('name') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Slug') }} <small class="muted" style="font-weight:400">{{ __('— made from the name if left empty') }}</small>
        <input id="slug" name="slug" type="text" value="{{ old('slug', $program->slug ?? '') }}">
        @error('slug') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Cover image') }} <small class="muted" style="font-weight:400">{{ __("— kept whole as WebP, never cropped (scaled down to fit 1280 px). Optional: the newest episode's picture is used without one.") }}</small>
        @if(isset($program) && $program->cover_image)
            <img src="{{ $program->cover_image_url }}" alt="" style="max-width:130px;max-height:130px;width:auto;height:auto;border-radius:8px">
            <span class="check-row" style="margin:0"><input type="checkbox" name="remove_cover_image" value="1"> {{ __('Remove current cover image') }}</span>
        @endif
        <div class="tag-view-toggle" style="align-self:flex-start">
            <button type="button" class="{{ old('cover_image_url') ? '' : 'active' }}" data-image-mode-tab="upload" data-image-field="cover_image">{{ __('Upload') }}</button>
            <button type="button" class="{{ old('cover_image_url') ? 'active' : '' }}" data-image-mode-tab="url" data-image-field="cover_image">{{ __('Paste URL') }}</button>
        </div>
        <input id="cover_image" name="cover_image" type="file" accept="image/*" data-image-mode-panel="upload" data-image-field="cover_image" @if(old('cover_image_url')) hidden disabled @endif>
        <input name="cover_image_url" type="text" placeholder="https://…" value="{{ old('cover_image_url') }}" data-image-mode-panel="url" data-image-field="cover_image" @unless(old('cover_image_url')) hidden disabled @endunless>
        @error('cover_image') <em>{{ $message }}</em> @enderror
        @error('cover_image_url') <em>{{ $message }}</em> @enderror
    </label>
    <label class="full">{{ __('Description') }}
        <textarea id="description" name="description">{{ old('description', $program->description ?? '') }}</textarea>
        @error('description') <em>{{ $message }}</em> @enderror
    </label>
</div>

<button type="submit" class="primary-button">{{ isset($program) ? __('Save changes') : __('Create podcast') }}</button>
