@csrf

<div class="form-grid" style="margin-bottom:24px">
    <label>Name
        <input id="name" name="name" type="text" value="{{ old('name', $category->name ?? '') }}" required>
        @error('name') <em>{{ $message }}</em> @enderror
    </label>
    <label>Slug
        <input id="slug" name="slug" type="text" value="{{ old('slug', $category->slug ?? '') }}" required>
        @error('slug') <em>{{ $message }}</em> @enderror
    </label>
    <label>Icon
        <input id="icon" name="icon" type="text" value="{{ old('icon', $category->icon ?? '') }}">
        @error('icon') <em>{{ $message }}</em> @enderror
    </label>
    <label>Sort order
        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
        @error('sort_order') <em>{{ $message }}</em> @enderror
    </label>
    <label class="full">Description
        <textarea id="description" name="description">{{ old('description', $category->description ?? '') }}</textarea>
        @error('description') <em>{{ $message }}</em> @enderror
    </label>
</div>

<button type="submit" class="primary-button">Save</button>
