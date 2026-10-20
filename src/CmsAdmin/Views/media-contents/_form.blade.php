@csrf

@php
    // A new episode added from a podcast's page starts public, so "Publish" publishes it; "Save draft" still stashes it as private.
    $currentStatus = old('status', $mediaContent->status ?? (($podcast ?? null) ? 'public' : 'private'));
    $statusTone = ['public' => 'green', 'scheduled' => 'amber', 'private' => 'slate'][$currentStatus];
    // A length in seconds as m:ss (h:mm:ss from an hour up); nothing for no length.
    $formatDuration = fn ($total) => ($total === null || $total === '') ? '' : (
        (int) $total >= 3600
            ? sprintf('%d:%02d:%02d', intdiv((int) $total, 3600), intdiv((int) $total % 3600, 60), (int) $total % 60)
            : sprintf('%d:%02d', intdiv((int) $total, 60), (int) $total % 60)
    );
    $durationSeconds = old('duration_seconds', $mediaContent->duration_seconds ?? '');
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
    @php
        $newTitle = ! isset($mediaContent) && ! ($podcast ?? null);
        // From the Article page an edit's header names the category too; its top bar keeps the item's own title.
        $editTitle = isset($mediaContent) && ($fromMusic ?? false);
    @endphp
    <strong class="editor-topbar-title" @if($newTitle) data-title-template="{{ __('New :category Media Content', ['category' => ':category']) }}" data-title-plain="{{ __('New media content') }}" data-title-plain-crumb="{{ __('New Media Content') }}" @elseif($editTitle) data-title-template="{{ __('Edit :category Media Content', ['category' => ':category']) }}" data-title-plain-crumb="{{ __('Edit Media Content') }}" @endif>{{
        isset($mediaContent)
            ? $mediaContent->title
            : (($podcast ?? null) ? __('New episode') : (($titleCategory ?? null) ? __('New :category Media Content', ['category' => $titleCategory->titleName()]) : __('New media content')))
    }}</strong>
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

{{-- The Description card follows the height of the sidebar beside it (Tags): the sidebar is its full height — no
     scroll box of its own, the page scrolls — the main column is as tall as the taller of the two, the card takes the
     room left under Content, and the editor fills the card. One column (narrow screens): nothing to follow, the
     editor keeps its minimum. --}}
<style>
    .editor-layout > .editor-sidebar { position: static; max-height: none; overflow: visible; }
    .editor-layout > .editor-main { align-self: stretch; }
    .editor-block.description-grow { flex: 1 1 auto; display: flex; flex-direction: column; }
    .description-grow .rich-editor { flex: 1 1 auto; display: flex; flex-direction: column; height: auto; min-height: 0; }
    .description-grow .rich-editor .ql-editor { flex: 1 1 auto; height: auto; }
    .youtube-fill { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .youtube-fill[hidden] { display: none; }
</style>

<div class="editor-layout">
    <div class="editor-main">
        <input id="title" name="title" type="text" class="editor-title-input" placeholder="{{ ($podcast ?? null) ? __('Episode title') : __($titlePlaceholder ?? 'Title Song or Title Audio or Title Podcasts Eps or Title Ambient') }}" value="{{ old('title', $mediaContent->title ?? '') }}" required>
        {{-- A thin line under the title, where the Slug row's bottom border used to be. --}}
        <hr class="editor-title-rule" style="width:100%;border:0;border-top:1px solid var(--line);margin:-8px 0 0">
        @error('title') <em>{{ $message }}</em> @enderror
        @error('slug') <em>{{ $message }}</em> @enderror

        <div class="editor-block">
            <h4>{{ __('Content') }}</h4>
            <div class="form-grid">
                <input id="media_type" name="media_type" type="hidden" value="audio">
                <label class="full">{{ __('Duration (m:s)') }}
                    <small class="muted" style="font-weight:400">{{ __('Filled in automatically from the uploaded file or the YouTube link.') }}</small>
                    {{-- The hidden field carries the seconds the server saves; the box beside it only shows them as m:s (a disabled box is never sent). --}}
                    <input id="duration_seconds" name="duration_seconds" type="hidden" value="{{ $durationSeconds }}">
                    <input type="text" value="{{ $formatDuration($durationSeconds) }}" placeholder="0:00" disabled data-duration-display>
                    @error('duration_seconds') <em>{{ $message }}</em> @enderror
                </label>
                <label class="full">{{ __('Stream / content') }}
                    <small class="muted" style="font-weight:400">{{ __('Maximum 100 MB. Uploads are saved immediately and converted to Opus by the queue worker.') }}</small>
                    @if(isset($mediaContent) && in_array($mediaContent->processing_status, ['processing', 'failed'], true))
                        @php
                            $audioFailed = $mediaContent->processing_status === 'failed';
                            $failedLabel = $mediaContent->source_url ? __('YouTube audio could not be fetched — paste the link again to retry') : __('Audio conversion failed — upload again to retry');
                        @endphp
                        {{-- While the audio is processing the page asks the server how it is doing and updates this badge (the script in the layout). --}}
                        <span class="badge {{ $audioFailed ? 'slate' : 'amber' }}" data-status-badge data-state="{{ $mediaContent->processing_status }}" data-status-url="{{ route('admin.media-contents.processing-status', $mediaContent) }}" data-labels="{{ json_encode(['processing' => __('Audio processing'), 'ready' => __('Audio ready — reload the page to play it'), 'failed' => $failedLabel], JSON_UNESCAPED_UNICODE) }}" @if($audioFailed) title="{{ $mediaContent->processing_error }}" @endif>{{ $audioFailed ? $failedLabel : __('Audio processing') }}</span>
                        @if($audioFailed && $mediaContent->processing_error)
                            <small class="muted" data-retry-reason style="display:block;font-weight:400;overflow-wrap:anywhere">{{ \Illuminate\Support\Str::limit($mediaContent->processing_error, 300) }}</small>
                        @endif
                        @if(auth('admin')->user()?->hasPermission('edit-media-content'))
                            <button type="submit" form="retry-audio-form" formnovalidate class="secondary-button compact" data-retry-button style="align-self:flex-start{{ $audioFailed ? '' : ';display:none' }}">{{ __('Retry') }}</button>
                        @endif
                    @endif
                    {{-- A YouTube link is not played from YouTube: its audio is fetched and kept as our own small file. --}}
                    @if(isset($mediaContent) && $mediaContent->source_url)
                        <small class="muted" style="font-weight:400">{{ __('Audio fetched from YouTube') }} · <a href="{{ $mediaContent->source_url }}" target="_blank" rel="noopener">{{ __('Open ↗') }}</a></small>
                    @endif
                    @if(isset($mediaContent) && $mediaContent->stream)
                        @php
                            $isExternalStream = str_starts_with($mediaContent->stream, 'http://') || str_starts_with($mediaContent->stream, 'https://');
                            $seconds = (int) $mediaContent->duration_seconds;
                            $length = $formatDuration($seconds);
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
                    {{-- A pasted YouTube link: its title, length and cover can be read from the YouTube Data API. The audio itself is still fetched by the server when the form is saved. --}}
                    <div class="youtube-fill" data-youtube-fill hidden
                         data-url="{{ route('admin.media-contents.youtube-details') }}"
                         data-auto="{{ \Src\Catalog\Services\YoutubeVideoDetails::configured() ? '1' : '0' }}"
                         data-loading="{{ __('Reading the video…') }}"
                         data-done="{{ __('Filled in from YouTube.') }}"
                         data-done-auto="{{ __('Empty fields filled in from YouTube.') }}"
                         data-live="{{ __('This is a live broadcast — its audio cannot be fetched.') }}"
                         data-failed="{{ __('Could not read that video.') }}">
                        <button type="button" class="secondary-button compact" data-youtube-fill-button>{{ __('Fill from YouTube') }}</button>
                        <small class="muted" data-youtube-fill-status aria-live="polite" style="font-weight:400;overflow-wrap:anywhere">{{ __('Title, length and cover are read from the link.') }}</small>
                    </div>
                </label>
            </div>
        </div>

        <div class="editor-block description-grow">
            <h4>{{ __('Description') }}</h4>
            <div class="rich-editor" data-rich-text></div>
            <textarea id="description" name="description" data-rich-text-source hidden>{{ old('description', $mediaContent->description ?? '') }}</textarea>
        </div>
    </div>

    <aside class="editor-sidebar">
        <div class="sidebar-box">
            <h4>{{ __('Featured image') }} <small class="muted" style="font-weight:400">{{ __('ratio 3:4') }}</small></h4>
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
            @php
                $lockedCategoryId = $lockedCategoryId ?? null;
                $hiddenFields = $hiddenFields ?? [];
                $podcastRequired = $podcastRequired ?? false;
                $lockedProgramId = $lockedProgramId ?? null;
            @endphp
            {{-- The Song form has nothing to choose here, so the field is out of sight; it stays in the page because the
                 scripts read the Category (the tag list, the title) and the form posts it. --}}
            <label @if(in_array('category', $hiddenFields, true)) style="display:none" @endif>{{ __('Category') }}
                {{-- "New Song" fixes the category; a disabled select posts nothing, so a hidden field carries it. --}}
                @if($lockedCategoryId)<input type="hidden" name="category_id" value="{{ $lockedCategoryId }}">@endif
                <select id="category_id" @if($lockedCategoryId) disabled @else name="category_id" required @endif>
                    <option value="">{{ __('Select a category…') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" data-title-name="{{ $category->titleName() }}" @selected((int) old('category_id', $mediaContent->category_id ?? $podcast?->category_id ?? $defaultCategoryId ?? '') === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <em>{{ $message }}</em> @enderror
            </label>
            {{-- "New Song" / "New Program" leave the Podcast (and Program also the Artist) field out; the form's script copes without it. --}}
            @unless(in_array('podcast', $hiddenFields, true))
                <label>
                    <span style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                        {{ __('Podcast Program') }}
                        {{-- A new podcast is made on its own page, in a new tab so this form isn't lost; reload here to pick it. --}}
                        @if(! $lockedProgramId && auth('admin')->user()?->hasPermission('create-programs'))
                            <a href="{{ route('admin.programs.create') }}" target="_blank" rel="noopener" class="primary-button compact" style="height:24px;padding:0 11px;font-size:9px;box-shadow:none" title="{{ __('Create a new podcast (opens in a new tab)') }}">{{ __('New') }}</a>
                        @endif
                    </span>
                    {{-- An episode edited from its podcast's page keeps that podcast; a disabled select posts nothing, so a hidden field carries it. --}}
                    @if($lockedProgramId)<input type="hidden" name="program_id" value="{{ $lockedProgramId }}">@endif
                    <select id="program_id" @if($lockedProgramId) disabled @else name="program_id" @endif>
                        {{-- "New Podcast" has no "No podcast": a placeholder shows until one is picked, and can't be picked again. --}}
                        @if($podcastRequired)
                            <option value="" disabled hidden @selected(! old('program_id', $mediaContent->program_id ?? $podcast?->id ?? ''))>{{ __('Select a podcast…') }}</option>
                        @else
                            <option value="">{{ __('No podcast') }}</option>
                        @endif
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" data-category-id="{{ $program->category_id }}" data-tag-id="{{ $program->tag_id }}" @selected((int) old('program_id', $mediaContent->program_id ?? $podcast?->id ?? '') === $program->id)>{{ $program->name }}</option>
                        @endforeach
                    </select>
                    @error('program_id') <em>{{ $message }}</em> @enderror
                </label>
            @endunless
            {{-- Tags are part of this card, under the Category: the tree follows the Category picked above. --}}
            {{-- Names the International / Local select below; the script leaves it out when the tree on show has no regions (News, Health). --}}
            <span data-tag-caption style="display:block;font-size:9px;font-weight:700;margin:0 0 6px">{{ __('Region') }}</span>
            @php $selectedTags = collect(old('tags', isset($mediaContent) ? $mediaContent->tags->pluck('id')->all() : [])); @endphp
            @if($tagTree->isEmpty())
                <p class="muted">{!! __('No tags yet — :link.', ['link' => '<a href="'.e(route('admin.tags.create')).'">'.e(__('create one')).'</a>']) !!}</p>
            @else
                {{-- International / Local, as a dropdown: picks which branch of the tree is listed (the other is hidden); only offered while the tree on show has them. --}}
                <label class="tag-region" style="margin:0 0 9px">
                    <select data-tag-region aria-label="{{ __('Filter tags by region') }}">
                        <option value="international">{{ __('International') }}</option>
                        <option value="local">{{ __('Local') }}</option>
                    </select>
                </label>
                {{-- Names what the list below starts with (Genre, under International); the script fills it in and shows it. --}}
                <span data-tag-tree-title style="display:none;font-size:9px;font-weight:700;margin:0 0 6px"></span>
                <div class="tag-tree" data-tag-tree>
                    {{-- The top row of News, Health, Economy/Finance, Ambient Music and Podcast is left out, so each lists only its own rows; Economy/Finance's two (International, Local) are radio buttons. --}}
                    @include('cms-admin::partials.tag-tree', [
                        'nodes' => $tagTree,
                        'selectedTags' => $selectedTags,
                        'foldRoots' => $categories->whereIn('slug', ['news', 'health', 'economyfinance', 'ambient-music', 'podcast'])->pluck('id')->map(fn ($id) => (int) $id)->all(),
                        'radioRoots' => $categories->where('slug', 'economyfinance')->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    ])
                </div>
                <p class="muted" style="margin:0;font-size:10px" data-tag-category-hint hidden>{{ __('Choose a category to pick its tags.') }}</p>
            @endif
        </div>

        {{-- The Artist has a card of its own, under Details (the News form leaves it out). --}}
        @unless(in_array('artist', $hiddenFields, true))
            @php $selectedArtists = collect(old('artist_ids', isset($mediaContent) ? $mediaContent->artists->pluck('id')->all() : []))->map(fn ($id) => (int) $id); @endphp
            <div class="sidebar-box">
                <label for="artist_search">{{ __('Artist') }}
                    {{-- Narrows the list below to the names that contain what is typed (the script); it is not sent with the form. --}}
                    <input id="artist_search" type="search" placeholder="{{ __('Search artist…') }}" aria-label="{{ __('Search artist') }}" autocomplete="off" data-artist-search>
                </label>
                {{-- Any number of artists can be ticked. --}}
                @if($artists->isEmpty())
                    <p class="muted" style="margin:0">{!! __('No artists yet — :link.', ['link' => '<a href="'.e(route('admin.artists.create')).'">'.e(__('create one')).'</a>']) !!}</p>
                @else
                    {{-- Five artists show; more scroll (the script sets the height from the real rows; this is the fallback). --}}
                    <div class="tag-tree" style="max-height:164px" data-artist-list>
                        @foreach($artists as $artist)
                            <label class="tag-tree-leaf" data-artist-row>
                                <input type="checkbox" name="artist_ids[]" value="{{ $artist->id }}" @checked($selectedArtists->contains($artist->id))>
                                <span>{{ $artist->stage_name ?: $artist->name }}</span>
                            </label>
                        @endforeach
                        <p class="muted" style="margin:6px 8px;font-size:10px" data-artist-empty hidden>{{ __('No matching artists.') }}</p>
                    </div>
                @endif
                @error('artist_ids') <em style="color:var(--pink);font-size:8px;font-style:normal">{{ $message }}</em> @enderror
                @error('artist_ids.*') <em style="color:var(--pink);font-size:8px;font-style:normal">{{ $message }}</em> @enderror
            </div>
        @endunless
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
    // switching category clears the tags of the tree it hides. With no category yet there is no tree to show,
    // so the empty box is left out and only the hint shows.
    (() => {
        const select = document.getElementById('category_id');
        const tree = document.querySelector('[data-tag-tree]');
        if (!select || !tree) return;
        const hint = document.querySelector('[data-tag-category-hint]');
        const roots = [...tree.children].filter(node => node.dataset.categoryId !== undefined);

        const apply = clear => {
            const id = select.value;
            // A category with no tags of its own doesn't narrow anything.
            const narrowed = roots.some(node => node.dataset.categoryId === id);
            roots.forEach(node => {
                // Trees that sit under no category are offered whatever the category is.
                const show = id !== '' && (!narrowed || node.dataset.categoryId === id || node.dataset.categoryId === '');
                node.style.display = show ? '' : 'none';
                if (!show && clear) node.querySelectorAll('input[type=checkbox], input[type=radio]').forEach(input => { input.checked = false; });
            });
            tree.style.display = roots.some(node => node.style.display !== 'none') ? '' : 'none';
            hint?.toggleAttribute('hidden', id !== '');
        };

        select.addEventListener('change', () => apply(true));
        apply(false);
    })();

    // Region filter for the tag picker: International or Local is always picked (there is no "all"). The other
    // branch is hidden wherever it appears (under Song, under Economy/Finance, …) and the picked one is listed
    // on its own: its row and the rows above it (Song) are left out, so the list starts at Genre. A region with
    // no branches of its own stays where it is. It only narrows what is shown: a ticked tag in a hidden branch
    // stays ticked. The dropdown is offered only while the tree on show has regions, and it starts on
    // International — or on the region an item already has a ticked tag in.
    (() => {
        const tree = document.querySelector('[data-tag-tree]');
        const select = document.querySelector('[data-tag-region]');
        if (!tree || !select) return;
        const field = select.closest('label');
        const picked = () => select.value;
        const nameOf = node => node.querySelector('span')?.textContent.trim().toLowerCase();
        const summaryOf = node => node.querySelector(':scope > summary');
        const childrenOf = node => node.querySelector(':scope > .tag-tree-children');
        const parentNode = node => node.parentElement.closest('details.tag-tree-node');
        // Only a region with rows of its own under it (Song > International > Genre) is picked with the radio buttons
        // above; a tree whose regions are single tags (Economy/Finance) lists them as radio buttons of its own.
        const regionNodes = [...tree.querySelectorAll('details.tag-tree-node')]
            .filter(node => ['international', 'local'].includes(nameOf(node)));
        const openBefore = new Map();
        const regionsOnShow = () => regionNodes.filter(node => node.closest('[data-category-id]').style.display !== 'none');
        const title = document.querySelector('[data-tag-tree-title]');
        const caption = document.querySelector('[data-tag-caption]');

        const labelOf = node => node.querySelector('span')?.textContent.trim();

        // A row that gives way to a caption or title: its own row (arrow, tick box, name) is left out and the rows
        // under it are listed, flush left. A tag that is ticked already stays ticked (a hidden box is still sent).
        const foldAway = node => {
            if (!openBefore.has(node)) openBefore.set(node, node.open);
            node.open = true;
            summaryOf(node).style.display = 'none';
            childrenOf(node).style.marginLeft = '0';
        };

        // The "Region" caption goes with the International / Local dropdown: it shows while it is offered. A tree
        // with no regions (News, Health, Economy/Finance, Ambient Music, Podcast) has none, so there is no caption. A tree
        // flagged to give way (those five) leaves its top row out: its own rows are listed on their own.
        const refreshCaption = () => {
            if (caption) caption.style.display = regionsOnShow().length ? 'block' : 'none';

            [...tree.children]
                .filter(node => node.hasAttribute('data-fold-root') && node.style.display !== 'none' && node.matches('details.tag-tree-node'))
                .forEach(foldAway);
        };

        // The title over the list names the rows under the picked region (Genre, under International), and those rows
        // give way to it: their own row (arrow, tick box, name) is left out and their children are listed, flush left.
        // A tag that is ticked already stays ticked (a hidden box is still sent). With no region on show, or the list
        // hidden, there is no title and every row stays.
        const refreshTitle = () => {
            if (!title) return;
            const region = regionsOnShow().find(node => nameOf(node) === picked() && node.matches('details'));
            const tops = !region || tree.style.display === 'none'
                ? []
                : [...childrenOf(region).children].filter(node => node.matches('details.tag-tree-node'));

            tops.forEach(foldAway);

            const text = tops.map(labelOf).filter(Boolean).join(' / ');
            title.textContent = text;
            title.style.display = text ? 'block' : 'none';
        };

        const apply = () => {
            field.style.display = regionsOnShow().length ? '' : 'none';

            // Back to the whole tree: restore the rows, indents and open state that were flattened.
            tree.querySelectorAll('details.tag-tree-node').forEach(node => {
                summaryOf(node).style.display = '';
                childrenOf(node).style.marginLeft = '';
                if (openBefore.has(node)) { node.open = openBefore.get(node); openBefore.delete(node); }
            });
            regionNodes.forEach(node => { node.style.display = nameOf(node) === picked() ? '' : 'none'; });

            regionNodes.filter(node => nameOf(node) === picked() && node.matches('details')).forEach(region => {
                for (let node = region; node; node = parentNode(node)) {
                    if (!openBefore.has(node)) openBefore.set(node, node.open);
                    node.open = true;
                    summaryOf(node).style.display = 'none';
                    childrenOf(node).style.marginLeft = '0';
                }
            });

            refreshTitle();
            refreshCaption();
        };

        // Start on the region an item already has a ticked tag in (a song filed under Local), else the first one.
        const startRegion = () => {
            const tagged = regionsOnShow().find(node => node.querySelector('input:checked'));
            select.value = tagged ? nameOf(tagged) : select.options[0].value;
        };

        select.addEventListener('change', apply);
        // A new category brings a different tree (whose ticked tags were cleared): start it afresh.
        document.getElementById('category_id')?.addEventListener('change', () => { startRegion(); apply(); });
        startRegion();
        apply();
    })();

    // Tags follow the Podcast Program: a program has a type (a tag under Podcast). While a program with a type is
    // picked — and its tag is in the list on show — the list is locked: only that tag is ticked and nothing can be
    // changed by hand. A disabled box is not sent with a form, so the ticked ones are added to what is sent. Picking
    // "No podcast", or a program with no type, unlocks the list and gives back the ticks it had before. A new item
    // starts with the tag of the program it starts on; an item being edited keeps the tags it has (locked) until its
    // program is changed.
    (() => {
        const program = document.getElementById('program_id');
        const tree = document.querySelector('[data-tag-tree]');
        if (!program || !tree || !program.form) return;
        const isNew = !program.form.querySelector('input[name="_method"]'); // an edit form is sent as PUT
        const boxes = () => [...tree.querySelectorAll('input[name="tags[]"]')];
        const onShow = box => { const top = box.closest('[data-category-id]'); return !top || top.style.display !== 'none'; };
        let before = null; // the boxes that were ticked before the list began to follow a program

        const follow = reset => {
            const id = program.selectedOptions[0]?.dataset.tagId;
            const next = id ? boxes().find(box => box.value === id) : null;

            if (next && onShow(next)) {
                if (!before) before = boxes().filter(box => box.checked);
                boxes().forEach(box => {
                    if (reset) box.checked = box === next;
                    box.disabled = true;
                });
            } else {
                boxes().forEach(box => {
                    if (before) box.checked = before.includes(box);
                    box.disabled = false;
                });
                before = null;
            }
        };

        program.addEventListener('change', () => follow(true));
        document.getElementById('category_id')?.addEventListener('change', () => follow(true));
        program.form.addEventListener('formdata', event => {
            boxes().filter(box => box.disabled && box.checked).forEach(box => event.formData.append('tags[]', box.value));
        });
        follow(isNew);
    })();

    // The radio buttons in the tag list (Economy/Finance's International and Local) pick one at a time; clicking the
    // one that is picked clears it again, so an item can have neither.
    (() => {
        const radios = [...document.querySelectorAll('[data-tag-tree] input[type=radio]')];
        const remember = () => radios.forEach(radio => { radio.dataset.was = radio.checked ? '1' : ''; });
        remember();
        radios.forEach(radio => radio.addEventListener('click', () => {
            if (radio.dataset.was === '1') radio.checked = false;
            remember();
        }));
    })();

    // Artist: the list shows five artists and scrolls beyond that; the search box narrows it to the names that
    // contain what is typed. A ticked artist stays in the list whatever is typed, so what is chosen is always on
    // show; Enter doesn't send the form.
    (() => {
        const search = document.querySelector('[data-artist-search]');
        const list = document.querySelector('[data-artist-list]');
        const rows = [...document.querySelectorAll('[data-artist-row]')];
        if (!search || !list || !rows.length) return;
        const empty = document.querySelector('[data-artist-empty]');

        // Tall enough for exactly five rows (measured, so it follows the font), box padding and border included.
        // Measured again once the page, fonts included, has loaded; a reading too small to be five rows (the styles
        // are not there yet) is ignored and the fallback height in the markup stays.
        const fifth = rows[4];
        const fit = () => {
            const box = getComputedStyle(list);
            const height = fifth.getBoundingClientRect().bottom - list.getBoundingClientRect().top
                + parseFloat(box.paddingBottom) + parseFloat(box.borderBottomWidth);
            if (height >= 100) list.style.maxHeight = `${Math.ceil(height)}px`;
        };
        if (fifth) {
            fit();
            window.addEventListener('load', fit);
        }

        search.addEventListener('input', () => {
            list.scrollTop = 0;
            const needle = search.value.trim().toLowerCase();
            let shown = 0;
            rows.forEach(row => {
                const show = !needle || row.textContent.toLowerCase().includes(needle) || row.querySelector('input').checked;
                row.style.display = show ? '' : 'none';
                if (show) shown++;
            });
            if (empty) empty.hidden = shown > 0;
        });
        search.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    })();

    // The title names the category picked — "New Song Media Content", "Edit Song Media Content" — in the page
    // header and the browser tab, and for a new item in the top bar too (an edited item's top bar keeps its own
    // title); with none picked it reads "New Media Content" / "Edit Media Content". (The server renders the first.)
    (() => {
        const title = document.querySelector('[data-title-template]');
        const select = document.getElementById('category_id');
        if (!title || !select) return;
        const crumb = document.querySelector('.breadcrumbs strong');

        select.addEventListener('change', () => {
            const picked = select.selectedOptions[0];
            const name = select.value ? (picked.dataset.titleName || picked.textContent).trim() : '';
            const named = name ? title.dataset.titleTemplate.replace(':category', name) : null;
            if (title.dataset.titlePlain !== undefined) title.textContent = named ?? title.dataset.titlePlain;
            if (crumb) crumb.textContent = named ?? title.dataset.titlePlainCrumb;
            document.title = document.title.replace(/^.*?( · )/, () => (named ?? title.dataset.titlePlainCrumb) + ' · ');
        });
    })();

    // A pasted YouTube link: "Fill from YouTube" reads its title, length and cover from the YouTube Data API (through
    // the server, which holds the key). Committing the link does it once by itself, into the fields still empty; the
    // button does it on request and replaces what the video has an answer for. The audio is fetched when the form is saved.
    (() => {
        const box = document.querySelector('[data-youtube-fill]');
        const link = document.querySelector('input[name="stream_url"]');
        if (!box || !link) return;
        const button = box.querySelector('[data-youtube-fill-button]');
        const status = box.querySelector('[data-youtube-fill-status]');
        const title = document.getElementById('title');
        const duration = document.getElementById('duration_seconds');
        const cover = document.querySelector('input[name="thumbnail_url"]');
        const coverFile = document.querySelector('input[name="thumbnail"]');
        const preview = document.querySelector('[data-thumbnail-preview]');
        const isYoutube = value => /^https?:\/\/([\w-]+\.)*(youtube\.com|youtube-nocookie\.com|youtu\.be)\//i.test(value.trim());
        let busy = false;
        let lastLink = '';

        // Offered only while the Paste URL box is on show and holds a YouTube link.
        const refresh = () => { box.hidden = link.hidden || !isYoutube(link.value); };

        const put = (input, value, replace) => {
            if (!input || value === null || value === undefined || value === '') return;
            if (!replace && input.value.trim() !== '') return;
            input.value = value;
        };

        const fill = async replace => {
            const url = link.value.trim();
            if (busy || !isYoutube(url)) return;
            busy = true;
            button.disabled = true;
            status.textContent = box.dataset.loading;

            try {
                const response = await fetch(box.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': link.form.querySelector('input[name="_token"]').value,
                    },
                    body: JSON.stringify({ url }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.message || box.dataset.failed);

                put(title, data.title, replace);
                put(duration, data.duration_seconds, replace);
                duration?.dispatchEvent(new Event('input', { bubbles: true }));

                // The cover goes in the Paste URL box (the server downloads it when the form is saved); it replaces a
                // cover that is picked or saved already only when asked for.
                const coverEmpty = preview?.hidden && !coverFile?.files?.length && !cover?.value.trim();
                if (data.thumbnail_url && cover && (replace || coverEmpty)) {
                    document.querySelector('[data-image-mode-tab="url"][data-image-field="thumbnail"]')?.click();
                    cover.value = data.thumbnail_url;
                    if (preview) { preview.src = data.thumbnail_url; preview.hidden = false; }
                }

                status.textContent = data.is_live ? box.dataset.live : (replace ? box.dataset.done : box.dataset.doneAuto);
            } catch (error) {
                status.textContent = error.message || box.dataset.failed;
            } finally {
                busy = false;
                button.disabled = false;
            }
        };

        link.addEventListener('input', refresh);
        link.addEventListener('change', () => {
            refresh();
            const url = link.value.trim();
            if (box.dataset.auto === '1' && isYoutube(url) && url !== lastLink) {
                lastLink = url;
                fill(false);
            }
        });
        // The Upload / Paste URL tabs show and hide the link box (the shared handler runs after this one).
        document.querySelectorAll('[data-image-mode-tab][data-image-field="stream"]').forEach(tab => tab.addEventListener('click', () => setTimeout(refresh)));
        button.addEventListener('click', () => { lastLink = link.value.trim(); fill(true); });
        refresh();
    })();
</script>
