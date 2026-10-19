@csrf

<div class="form-grid" style="margin-bottom:24px">
    <label>{{ __('Parent tag') }}
        <select id="parent_id" name="parent_id">
            <option value="">{{ __('— No parent (top-level) —') }}</option>
            @foreach($parentOptions as $option)
                <option value="{{ $option->id }}" data-category-id="{{ $option->category_id }}" @selected((int) old('parent_id', $tag->parent_id ?? '') === $option->id)>
                    {{ str_repeat('— ', $option->depth) }}{{ $option->name }}
                </option>
            @endforeach
        </select>
        @error('parent_id') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Category') }}
        <select id="category_id" name="category_id">
            <option value="">{{ __('— No category —') }}</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('category_id', $tag->category_id ?? '') === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <small class="muted" style="font-weight:400">{{ __('A tag under a parent always follows the parent\'s category.') }}</small>
        @error('category_id') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Name') }}
        <input id="name" name="name" type="text" value="{{ old('name', $tag->name ?? '') }}" required>
        @error('name') <em>{{ $message }}</em> @enderror
    </label>
    <label class="full">{{ __('Slug') }}
        <input id="slug" name="slug" type="text" value="{{ old('slug', $tag->slug ?? '') }}" required>
        <small class="muted" style="font-weight:400">{{ __('Only needs to be unique among tags with the same parent.') }}</small>
        @error('slug') <em>{{ $message }}</em> @enderror
    </label>
    <label class="full">{{ __('Card picture') }} <small class="muted" style="font-weight:400">{{ __('— cropped to WebP, 616×296') }}</small>
        @if(isset($tag) && $tag->image)
            <img src="{{ $tag->image_url }}" alt="" style="width:154px;height:74px;border-radius:8px;object-fit:cover">
            <span class="check-row" style="margin:0"><input type="checkbox" name="remove_image" value="1"> {{ __('Remove current picture') }}</span>
        @endif
        <div class="tag-view-toggle" style="align-self:flex-start">
            <button type="button" class="{{ old('image_url') ? '' : 'active' }}" data-image-mode-tab="upload" data-image-field="image">{{ __('Upload') }}</button>
            <button type="button" class="{{ old('image_url') ? 'active' : '' }}" data-image-mode-tab="url" data-image-field="image">{{ __('Paste URL') }}</button>
        </div>
        <input id="image" name="image" type="file" accept="image/*" data-image-mode-panel="upload" data-image-field="image" @if(old('image_url')) hidden disabled @endif>
        <input name="image_url" type="text" placeholder="https://…" value="{{ old('image_url') }}" data-image-mode-panel="url" data-image-field="image" @unless(old('image_url')) hidden disabled @endunless>
        @error('image') <em>{{ $message }}</em> @enderror
        @error('image_url') <em>{{ $message }}</em> @enderror
    </label>
</div>

<button type="submit" class="primary-button">{{ __('Save') }}</button>

<script>
    // With a parent picked the category is the parent's and can't be chosen.
    (() => {
        const parent = document.getElementById('parent_id');
        const category = document.getElementById('category_id');
        if (!parent || !category) return;

        const sync = () => {
            const inherited = parent.selectedOptions[0]?.dataset.categoryId ?? '';
            category.disabled = parent.value !== '';
            if (parent.value !== '') category.value = inherited;
        };

        parent.addEventListener('change', sync);
        sync();
    })();
</script>
