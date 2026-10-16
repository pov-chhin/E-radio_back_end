@csrf

<div class="form-grid" style="margin-bottom:24px">
    <label>{{ __('Name') }}
        <input id="name" name="name" type="text" value="{{ old('name', $radioChannel->name ?? '') }}" required>
        @error('name') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Stream URL') }} <small class="muted" style="font-weight:400">{{ __('— leave blank to save as a draft') }}</small>
        <input id="stream_url" name="stream_url" type="text" value="{{ old('stream_url', $radioChannel->stream_url ?? '') }}" placeholder="https://…">
        @error('stream_url') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Cover') }} <small class="muted" style="font-weight:400">{{ __('— kept whole as WebP, never cropped (4:3 ratio)') }}</small>
        @if(isset($radioChannel) && $radioChannel->cover)
            <img src="{{ $radioChannel->cover_url }}" alt="" style="max-width:130px;max-height:130px;width:auto;height:auto;border-radius:8px">
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
    <label>{{ __('Bitrate') }} <small class="muted" style="font-weight:400">(kbps)</small>
        <input id="bitrate" name="bitrate" type="number" min="0" value="{{ old('bitrate', $radioChannel->bitrate ?? '') }}">
        @error('bitrate') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Tags') }}
        <input id="tags" name="tags" type="text" value="{{ old('tags', $radioChannel->tags ?? '') }}" placeholder="{{ __('pop, news, khmer') }}">
        @error('tags') <em>{{ $message }}</em> @enderror
    </label>
    <label class="full">{{ __('Description') }}
        <textarea id="description" name="description">{{ old('description', $radioChannel->description ?? '') }}</textarea>
        @error('description') <em>{{ $message }}</em> @enderror
    </label>
</div>

<button type="submit" class="primary-button">{{ __('Save') }}</button>
