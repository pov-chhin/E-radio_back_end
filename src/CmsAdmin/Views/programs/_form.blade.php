@csrf

{{-- The same look as the media editor: a bar at the top with the save button, a big title with the slug under it,
     the text in a card, and the settings (Published, type, cover) in a sidebar. The slug follows the title as it
     is typed (script at the bottom). --}}
<style>
    /* As in the media editor: the sidebar is its full height (the page scrolls, not it), and the Description card
       grows to meet the bottom of it, the editor filling the card. */
    .editor-layout > .editor-sidebar { position: static; max-height: none; overflow: visible; }
    .editor-layout > .editor-main { align-self: stretch; }
    .editor-block.description-grow { flex: 1 1 auto; display: flex; flex-direction: column; }
    .description-grow .rich-editor { flex: 1 1 auto; display: flex; flex-direction: column; height: auto; min-height: 0; }
    .description-grow .rich-editor .ql-editor { flex: 1 1 auto; height: auto; }
</style>

@php $published = (bool) old('is_published', $program->is_published ?? false); @endphp
<div class="editor-topbar">
    <a href="{{ route('admin.programs.index') }}" class="icon-button" aria-label="{{ __('Back to podcasts') }}"><i class="fa fa-arrow-left"></i></a>
    <span class="badge {{ $published ? 'green' : 'slate' }}" data-published-badge>{{ $published ? __('Published') : __('Draft') }}</span>
    <strong class="editor-topbar-title">{{ isset($program) ? $program->name : __('New Podcast') }}</strong>
    <div class="editor-topbar-actions">
        <button type="submit" class="primary-button">{{ isset($program) ? __('Save changes') : __('Create podcast') }}</button>
    </div>
</div>

<div class="editor-layout">
    <div class="editor-main">
        <div>
            <input id="name" name="name" type="text" class="editor-title-input" placeholder="{{ __('Podcast title') }}" aria-label="{{ __('Title') }}" value="{{ old('name', $program->name ?? '') }}" required autocomplete="off">
            @error('name') <em class="field-error">{{ $message }}</em> @enderror
        </div>
        <div>
            <label class="editor-slug-row">{{ __('Slug') }}
                <input id="slug" name="slug" type="text" placeholder="{{ __('Made from the title as you type') }}" value="{{ old('slug', $program->slug ?? '') }}" autocomplete="off" spellcheck="false">
            </label>
            @error('slug') <em class="field-error">{{ $message }}</em> @enderror
        </div>

        @php
            $description = (string) old('description', $program->description ?? '');
            // The editor works in HTML. A description written before it was plain text, so each of its lines becomes
            // a paragraph here instead of running together.
            if ($description !== '' && $description === strip_tags($description)) {
                $description = collect(preg_split('/\R/', $description))->map(fn ($line) => '<p>'.e($line).'</p>')->implode('');
            }
        @endphp
        <div class="editor-block description-grow">
            <h4>{{ __('Description') }}</h4>
            <div class="rich-editor" data-rich-text></div>
            <textarea id="description" name="description" data-rich-text-source hidden>{{ $description }}</textarea>
            @error('description') <em class="field-error">{{ $message }}</em> @enderror
        </div>

    </div>

    <aside class="editor-sidebar">
        <div class="sidebar-box">
            <h4>{{ __('Cover image') }}</h4>
            @php $hasCover = isset($program) && $program->cover_image; @endphp
            {{-- The current cover; the script below swaps in the picture just chosen (a file, or an address pasted in). --}}
            <img src="{{ $hasCover ? $program->cover_image_url : '' }}" alt="{{ __('Cover image preview') }}" data-cover-preview style="display:block;max-width:100%;max-height:220px;width:auto;height:auto;border-radius:9px;margin-bottom:10px" @unless($hasCover) hidden @endunless>
            @if($hasCover)
                <span class="check-row" style="margin:0 0 10px"><input type="checkbox" name="remove_cover_image" value="1"> {{ __('Remove current cover image') }}</span>
            @endif
            <div class="tag-view-toggle" style="margin-bottom:8px">
                <button type="button" class="{{ old('cover_image_url') ? '' : 'active' }}" data-image-mode-tab="upload" data-image-field="cover_image">{{ __('Upload') }}</button>
                <button type="button" class="{{ old('cover_image_url') ? 'active' : '' }}" data-image-mode-tab="url" data-image-field="cover_image">{{ __('Paste URL') }}</button>
            </div>
            <input id="cover_image" name="cover_image" type="file" accept="image/*" data-image-mode-panel="upload" data-image-field="cover_image" @if(old('cover_image_url')) hidden disabled @endif>
            <input name="cover_image_url" type="text" placeholder="https://…" value="{{ old('cover_image_url') }}" data-image-mode-panel="url" data-image-field="cover_image" @unless(old('cover_image_url')) hidden disabled @endunless>
            @error('cover_image') <em class="field-error">{{ $message }}</em> @enderror
            @error('cover_image_url') <em class="field-error">{{ $message }}</em> @enderror
            <p class="muted" style="font-size:9px;line-height:1.5;margin:10px 0 0">{{ __("Kept whole as WebP, never cropped (scaled down to fit 1280 px). Optional: the newest episode's picture is used without one.") }}</p>
        </div>

        <div class="sidebar-box">
            <h4>{{ __('Status') }}</h4>
            <label class="toggle-setting" style="margin-bottom:0">
                <span><b>{{ __('Published') }}</b><small>{{ __('Listed in the app once this is on and the podcast has at least one public episode.') }}</small></span>
                <span class="switch"><input type="checkbox" name="is_published" value="1" data-published-switch @checked($published)><i></i></span>
            </label>
        </div>

        <div class="sidebar-box">
            <h4>{{ __('Details') }}</h4>
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
        </div>
    </aside>
</div>

<script>
    // Shows the cover as it will be: the file just chosen, or the address just pasted (once it loads as a picture),
    // following the Upload / Paste URL tab; with neither it shows the current cover again, or nothing.
    (() => {
        const preview = document.querySelector('[data-cover-preview]');
        const file = document.getElementById('cover_image');
        const address = document.querySelector('input[name="cover_image_url"]');
        if (!preview || !file || !address) return;
        const original = preview.getAttribute('src') || '';
        let objectUrl = null;
        let timer = null;

        const show = src => { preview.src = src; preview.hidden = !src; };
        const refresh = () => {
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }

            if (!address.disabled && /^https?:\/\/\S+$/i.test(address.value.trim())) {
                show(address.value.trim());
            } else if (!file.disabled && file.files && file.files[0]) {
                objectUrl = URL.createObjectURL(file.files[0]);
                show(objectUrl);
            } else {
                show(original);
            }
        };

        file.addEventListener('change', refresh);
        address.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(refresh, 400); });
        // An address that is not a picture shows nothing rather than a broken image.
        preview.addEventListener('error', () => { if (preview.getAttribute('src') !== original) show(original); });
        // The tabs flip which of the two fields counts; wait until they have, then look again.
        document.querySelectorAll('[data-image-mode-tab][data-image-field="cover_image"]').forEach(tab => tab.addEventListener('click', () => setTimeout(refresh, 0)));
        refresh();
    })();

    // The badge in the top bar says what the Published switch says, as soon as it is flipped.
    (() => {
        const toggle = document.querySelector('[data-published-switch]');
        const badge = document.querySelector('[data-published-badge]');
        if (!toggle || !badge) return;
        const words = { published: @json(__('Published')), draft: @json(__('Draft')) };

        toggle.addEventListener('change', () => {
            badge.textContent = toggle.checked ? words.published : words.draft;
            badge.classList.toggle('green', toggle.checked);
            badge.classList.toggle('slate', !toggle.checked);
        });
    })();

    // The slug follows the title as it is typed, in the form the server would make of it: Latin letters and digits,
    // words joined by dashes. A Khmer title has no Latin letters to make one of, so it leaves the slug empty and the
    // server fills it in. Typing in the slug takes it over; emptying it hands it back to the title. A podcast that
    // already has a slug keeps it, since changing the slug would change the address it is known by.
    (() => {
        const title = document.getElementById('name');
        const slug = document.getElementById('slug');
        if (!title || !slug) return;
        // The same steps as the server's Str::slug: underscores and spaces become dashes, "@" becomes "at", other
        // punctuation drops out ("It's 24/7" is "its-247"), and the dashes are tidied.
        const slugify = text => text.normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase()
            .replace(/_+/g, '-').replace(/@/g, '-at-').replace(/[^a-z0-9\s-]+/g, '').replace(/[\s-]+/g, '-').replace(/^-+|-+$/g, '');
        let following = slug.value === '';

        title.addEventListener('input', () => { if (following) slug.value = slugify(title.value); });
        slug.addEventListener('input', () => { following = slug.value === ''; });
    })();
</script>
