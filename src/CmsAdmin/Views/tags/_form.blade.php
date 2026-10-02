@csrf

<div class="form-grid" style="margin-bottom:24px">
    <label>Parent tag
        <select id="parent_id" name="parent_id">
            <option value="">— No parent (top-level) —</option>
            @foreach($parentOptions as $option)
                <option value="{{ $option->id }}" data-category-id="{{ $option->category_id }}" @selected((int) old('parent_id', $tag->parent_id ?? '') === $option->id)>
                    {{ str_repeat('— ', $option->depth) }}{{ $option->name }}
                </option>
            @endforeach
        </select>
        @error('parent_id') <em>{{ $message }}</em> @enderror
    </label>
    <label>Category
        <select id="category_id" name="category_id">
            <option value="">— No category —</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('category_id', $tag->category_id ?? '') === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <small class="muted" style="font-weight:400">A tag under a parent always follows the parent's category.</small>
        @error('category_id') <em>{{ $message }}</em> @enderror
    </label>
    <label>Name
        <input id="name" name="name" type="text" value="{{ old('name', $tag->name ?? '') }}" required>
        @error('name') <em>{{ $message }}</em> @enderror
    </label>
    <label class="full">Slug
        <input id="slug" name="slug" type="text" value="{{ old('slug', $tag->slug ?? '') }}" required>
        <small class="muted" style="font-weight:400">Only needs to be unique among tags with the same parent.</small>
        @error('slug') <em>{{ $message }}</em> @enderror
    </label>
</div>

<button type="submit" class="primary-button">Save</button>

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
