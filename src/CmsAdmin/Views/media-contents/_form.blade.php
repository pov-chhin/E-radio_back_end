@csrf

@php
    // A new episode added from a podcast's page starts public, so "Publish" publishes it; "Save draft" still stashes it as private.
    $currentStatus = old('status', $mediaContent->status ?? (($podcast ?? null) ? 'public' : 'private'));
    $statusTone = ['public' => 'green', 'scheduled' => 'amber', 'private' => 'slate'][$currentStatus];
@endphp

<div class="editor-topbar">
    @if($podcast ?? null)
        <input type="hidden" name="podcast" value="{{ $podcast->id }}">
        <a href="{{ route('admin.programs.edit', $podcast) }}" class="icon-button" aria-label="{{ __('Back to :name', ['name' => $podcast->name]) }}"><i class="fa fa-arrow-left"></i></a>
    @elseif($fromMusic ?? false)
        <input type="hidden" name="from" value="music">
        <a href="{{ route('admin.music.index') }}" class="icon-button" aria-label="{{ __('Back to articles') }}"><i class="fa fa-arrow-left"></i></a>
    @else
        <a href="{{ route('admin.media-contents.index') }}" class="icon-button" aria-label="{{ __('Back to media content') }}"><i class="fa fa-arrow-left"></i></a>
    @endif
    <span class="badge {{ $statusTone }}" data-status-badge>{{ __(ucfirst($currentStatus)) }}</span>
    <strong class="editor-topbar-title">{{ isset($mediaContent) ? $mediaContent->title : (($podcast ?? null) ? __('New episode') : __('New media content')) }}</strong>
    <div class="editor-topbar-actions">
        <button type="submit" class="secondary-button" data-save-draft>{{ __('Save draft') }}</button>
        <button type="submit" class="primary-button">{{ isset($mediaContent) ? __('Update') : __('Publish') }}</button>
    </div>
</div>

<div class="upload-progress-overlay" data-upload-progress hidden aria-live="polite" aria-busy="true">
    <div class="upload-progress-card">
        <span class="upload-progress-icon"><i class="fa fa-cloud-upload"></i></span>
        <h3 data-upload-title>{{ __('Uploading media') }}</h3>
        <p data-upload-message>{{ __('Please keep this tab open while the file is sent to the server.') }}</p>
        <div class="upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-upload-track>
            <i data-upload-bar></i>
        </div>
        <div class="upload-progress-meta">
            <strong data-upload-percent>0%</strong>
            <span data-upload-size>{{ __('Preparing upload…') }}</span>
        </div>
        <button type="button" class="secondary-button" data-upload-cancel>{{ __('Cancel upload') }}</button>
    </div>
</div>

<div class="editor-layout">
    <div class="editor-main">
        <input id="title" name="title" type="text" class="editor-title-input" placeholder="{{ ($podcast ?? null) ? __('Episode title') : __('Media title') }}" value="{{ old('title', $mediaContent->title ?? '') }}" required>
        <label class="editor-slug-row">{{ __('Slug') }}
            <input id="slug" name="slug" type="text" value="{{ old('slug', $mediaContent->slug ?? '') }}" placeholder="{{ __('Made from the title if left empty') }}" pattern="[A-Za-z0-9_-]+" title="{{ __('Use letters, numbers, dashes, or underscores.') }}" data-slug-input>
        </label>
        @error('title') <em>{{ $message }}</em> @enderror
        @error('slug') <em>{{ $message }}</em> @enderror

        <div class="editor-block">
            <h4>{{ __('Content') }}</h4>
            <div class="form-grid">
                <input id="media_type" name="media_type" type="hidden" value="audio">
                <label class="full">{{ __('Duration (seconds)') }}
                    <small class="muted" style="font-weight:400">{{ __('Filled in automatically from the uploaded file — override only if it looks wrong.') }}</small>
                    <input id="duration_seconds" name="duration_seconds" type="number" min="0" value="{{ old('duration_seconds', $mediaContent->duration_seconds ?? '') }}">
                    @error('duration_seconds') <em>{{ $message }}</em> @enderror
                </label>
                <label class="full">{{ __('Stream / content') }}
                    <small class="muted" style="font-weight:400">{{ __('Maximum 100 MB. Uploads are saved immediately and converted to Opus by the queue worker.') }}</small>
                    @if(isset($mediaContent) && $mediaContent->processing_status === 'processing')
                        <span class="badge amber">{{ __('Audio processing') }}</span>
                    @elseif(isset($mediaContent) && $mediaContent->processing_status === 'failed')
                        <span class="badge slate" title="{{ $mediaContent->processing_error }}">{{ __('Audio conversion failed — upload again to retry') }}</span>
                    @endif
                    @if(isset($mediaContent) && $mediaContent->stream)
                        @php
                            $isExternalStream = str_starts_with($mediaContent->stream, 'http://') || str_starts_with($mediaContent->stream, 'https://');
                            $seconds = (int) $mediaContent->duration_seconds;
                            $length = $seconds >= 3600
                                ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
                                : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
                        @endphp
                        <div class="current-file">
                            @if($mediaContent->processing_status === 'ready' && ! $mediaContent->stream_missing)
                                <button type="button" class="secondary-button compact" data-preview-toggle data-stream-url="{{ $mediaContent->stream_url }}"><i class="fa fa-play"></i> {{ __('Play') }}</button>
                            @endif
                            <div class="current-file-info">
                                <b title="{{ $mediaContent->stream }}">{{ $isExternalStream ? parse_url($mediaContent->stream, PHP_URL_HOST).' · ' : '' }}{{ basename(parse_url($mediaContent->stream, PHP_URL_PATH) ?: $mediaContent->stream) }}</b>
                                <small class="muted">
                                    {{ $isExternalStream ? __('External link') : __('Uploaded file') }}
                                    @unless($isExternalStream) · {{ $mediaContent->stream_size_label }} @endunless
                                    @if($seconds > 0) · {{ $length }} @endif
                                </small>
                            </div>
                            @if($mediaContent->stream_missing)
                                <span class="badge amber" title="{{ __("The record still points at this file, but it is no longer on the server's storage. Upload it again.") }}">{{ __('File missing from storage') }}</span>
                            @else
                                <a href="{{ $mediaContent->stream_url }}" target="_blank" rel="noopener">{{ __('Open ↗') }}</a>
                            @endif
                        </div>
                    @endif
                    <div class="tag-view-toggle" style="align-self:flex-start">
                        <button type="button" class="{{ old('stream_url') ? '' : 'active' }}" data-image-mode-tab="upload" data-image-field="stream">{{ __('Upload') }}</button>
                        <button type="button" class="{{ old('stream_url') ? 'active' : '' }}" data-image-mode-tab="url" data-image-field="stream">{{ __('Paste URL') }}</button>
                    </div>
                    <input id="stream" name="stream" type="file" accept="audio/*,video/*" data-image-mode-panel="upload" data-image-field="stream" @if(old('stream_url')) hidden disabled @endif>
                    <input name="stream_url" type="text" placeholder="https://…" value="{{ old('stream_url') }}" data-image-mode-panel="url" data-image-field="stream" @unless(old('stream_url')) hidden disabled @endunless>
                    @error('stream') <em>{{ $message }}</em> @enderror
                    @error('stream_url') <em>{{ $message }}</em> @enderror
                </label>
            </div>
        </div>

        <div class="editor-block">
            <h4>{{ __('Description') }}</h4>
            <div class="rich-editor" data-rich-text></div>
            <textarea id="description" name="description" data-rich-text-source hidden>{{ old('description', $mediaContent->description ?? '') }}</textarea>
        </div>

        {{-- The moods of the Ambient Music category, for any content to carry. --}}
        <div class="editor-block" data-ambient-card>
            <h4>{{ __('Ambient Music') }}</h4>
            <p class="muted" style="margin:0 0 12px;font-size:10px">{{ __('Tick the moods this suits — listeners find it under Ambient Music.') }}</p>
            @php $ambientSelected = collect(old('tags', isset($mediaContent) ? $mediaContent->tags->pluck('id')->all() : [])); @endphp
            @if($ambientTags->isEmpty())
                <p class="muted">{!! __('No Ambient Music tags yet — :link.', ['link' => '<a href="'.e(route('admin.tags.create')).'">'.e(__('create one')).'</a>']) !!}</p>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px 18px">
                    @foreach($ambientTags as $ambientTag)
                        <label class="check-row" style="margin:0"><input type="checkbox" name="tags[]" value="{{ $ambientTag->id }}" @checked($ambientSelected->contains($ambientTag->id))> {{ $ambientTag->name }}</label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <aside class="editor-sidebar">
        <div class="sidebar-box">
            <h4>{{ __('Featured image') }} <small class="muted" style="font-weight:400">{{ __('— shown in full, WebP, up to 1280px') }}</small></h4>
            <img
                src="{{ isset($mediaContent) && $mediaContent->thumbnail ? $mediaContent->thumbnail_url : '' }}"
                alt="{{ __('Featured image preview') }}"
                class="sidebar-thumb-preview"
                data-thumbnail-preview
                @unless(isset($mediaContent) && $mediaContent->thumbnail) hidden @endunless
            >
            @if(isset($mediaContent) && $mediaContent->thumbnail)
                <span class="check-row" style="margin:0 0 10px"><input type="checkbox" name="remove_thumbnail" value="1"> {{ __('Remove current thumbnail') }}</span>
            @endif
            <div class="tag-view-toggle" style="margin-bottom:8px">
                <button type="button" class="{{ old('thumbnail_url') ? '' : 'active' }}" data-image-mode-tab="upload" data-image-field="thumbnail">{{ __('Upload') }}</button>
                <button type="button" class="{{ old('thumbnail_url') ? 'active' : '' }}" data-image-mode-tab="url" data-image-field="thumbnail">{{ __('Paste URL') }}</button>
            </div>
            <input id="thumbnail" name="thumbnail" type="file" accept="image/*" data-image-mode-panel="upload" data-image-field="thumbnail" data-thumbnail-input @if(old('thumbnail_url')) hidden disabled @endif>
            <input name="thumbnail_url" type="text" placeholder="https://…" value="{{ old('thumbnail_url') }}" data-image-mode-panel="url" data-image-field="thumbnail" @unless(old('thumbnail_url')) hidden disabled @endunless>
            @error('thumbnail') <em>{{ $message }}</em> @enderror
            @error('thumbnail_url') <em>{{ $message }}</em> @enderror
        </div>

        <div class="sidebar-box">
            <h4>{{ __('Status') }}</h4>
            <label>{{ __('Visibility') }}
                <select name="status" data-schedule-status required>
                    <option value="private" @selected($currentStatus === 'private')>{{ __('Private — not visible to listeners') }}</option>
                    <option value="public" @selected($currentStatus === 'public')>{{ __('Public — visible now') }}</option>
                    <option value="scheduled" @selected($currentStatus === 'scheduled')>{{ __('Scheduled — visible at a future date') }}</option>
                </select>
                @error('status') <em>{{ $message }}</em> @enderror
            </label>
            <label data-schedule-field @unless($currentStatus === 'scheduled') hidden @endunless>{{ __('Publish at') }}
                <small class="muted" style="font-weight:400">{{ __('Your local time (:timezone).', ['timezone' => config('app.admin_timezone')]) }}</small>
                <input type="datetime-local" name="published_at" value="{{ old('published_at', isset($mediaContent) ? $mediaContent->published_at_local?->format('Y-m-d\TH:i') : null) }}">
                @error('published_at') <em>{{ $message }}</em> @enderror
            </label>
            @if(isset($mediaContent) && $mediaContent->status === 'public')
                <p class="muted" style="font-size:9px;margin:8px 0 0">{{ __('Public :time', ['time' => $mediaContent->published_at->diffForHumans()]) }}</p>
            @endif
        </div>

        <div class="sidebar-box">
            <h4>{{ __('Details') }}</h4>
            <label>{{ __('Category') }}
                <select id="category_id" name="category_id" required>
                    <option value="">{{ __('Select a category…') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @if($category->id === $ambientCategoryId) data-elsewhere="1" @endif @selected((int) old('category_id', $mediaContent->category_id ?? $podcast?->category_id ?? $defaultCategoryId ?? '') === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Podcast') }}
                <select id="program_id" name="program_id">
                    <option value="">{{ __('No podcast') }}</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" data-category-id="{{ $program->category_id }}" @selected((int) old('program_id', $mediaContent->program_id ?? $podcast?->id ?? '') === $program->id)>{{ $program->name }}</option>
                    @endforeach
                </select>
                @error('program_id') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Artist') }}
                <select id="artist_id" name="artist_id">
                    <option value="">{{ __('No artist') }}</option>
                    @foreach($artists as $artist)
                        <option value="{{ $artist->id }}" @selected((int) old('artist_id', $mediaContent->artist_id ?? '') === $artist->id)>{{ $artist->stage_name ?: $artist->name }}</option>
                    @endforeach
                </select>
                @error('artist_id') <em>{{ $message }}</em> @enderror
            </label>
        </div>

        <div class="sidebar-box">
            <h4>{{ __('Tags') }}</h4>
            @php $selectedTags = collect(old('tags', isset($mediaContent) ? $mediaContent->tags->pluck('id')->all() : [])); @endphp
            @if($tagTree->isEmpty())
                <p class="muted">{!! __('No tags yet — :link.', ['link' => '<a href="'.e(route('admin.tags.create')).'">'.e(__('create one')).'</a>']) !!}</p>
            @else
                <label class="tag-search">
                    <span class="fa fa-search" aria-hidden="true"></span>
                    <input type="search" placeholder="{{ __('Search tags…') }}" aria-label="{{ __('Search tags') }}" data-tag-search>
                </label>
                <div class="tag-tree" data-tag-tree>
                    {{-- The Ambient Music tree is picked in its own card under the description. --}}
                    @include('cms-admin::partials.tag-tree', ['nodes' => $tagTree->reject(fn ($node) => $ambientCategoryId !== null && $node->category_id === $ambientCategoryId), 'selectedTags' => $selectedTags])
                </div>
                <p class="tag-search-empty muted" data-tag-search-empty hidden>{{ __('No matching tags.') }}</p>
                <p class="muted" data-tag-category-hint hidden>{{ __('Choose a category to pick its tags.') }}</p>
                <p class="muted" data-tag-elsewhere-hint hidden>{{ __('Ambient Music tags are picked in the Ambient Music card under the description.') }}</p>
            @endif
        </div>
    </aside>
</div>

<script>
    // Programs follow the Category: only the programs filed under the picked category are offered, and
    // switching category drops a program that no longer fits. A saved item keeps the program it already
    // has when the page opens, even one from another category, so nothing is unlinked by looking at it.
    (() => {
        const category = document.getElementById('category_id');
        const program = document.getElementById('program_id');
        if (!category || !program) return;
        const options = [...program.options].filter(option => option.value !== '');

        const apply = initial => {
            options.forEach(option => {
                const show = option.dataset.categoryId === category.value || (initial && option.defaultSelected);
                option.hidden = !show;
                option.disabled = !show;
            });
            if (!initial && program.selectedOptions[0]?.disabled) program.value = '';
        };

        category.addEventListener('change', () => apply(false));
        apply(true);
    })();

    // Tags follow the Category: only the tree that belongs to the picked category is shown, and
    // switching category clears the tags of the tree it hides.
    (() => {
        const select = document.getElementById('category_id');
        const tree = document.querySelector('[data-tag-tree]');
        if (!select || !tree) return;
        const hint = document.querySelector('[data-tag-category-hint]');
        const elsewhereHint = document.querySelector('[data-tag-elsewhere-hint]');
        const roots = [...tree.children].filter(node => node.dataset.categoryId !== undefined);

        const apply = clear => {
            const id = select.value;
            // A category whose tags are picked in a card of their own has nothing to show here.
            const elsewhere = select.selectedOptions[0]?.dataset.elsewhere === '1';
            // A category with no tags of its own doesn't narrow anything.
            const narrowed = elsewhere || roots.some(node => node.dataset.categoryId === id);
            roots.forEach(node => {
                // Trees that sit under no category are offered whatever the category is.
                const show = id !== '' && (!narrowed || node.dataset.categoryId === id || node.dataset.categoryId === '');
                node.style.display = show ? '' : 'none';
                if (!show && clear) node.querySelectorAll('input[type=checkbox]').forEach(input => { input.checked = false; });
            });
            hint?.toggleAttribute('hidden', id !== '');
            elsewhereHint?.toggleAttribute('hidden', !elsewhere);
        };

        select.addEventListener('change', () => apply(true));
        apply(false);
    })();
</script>
