{{-- Recursive: $nodes is a Tag::tree() collection (or a node's ->children), $selectedTags is a collection of checked ids. Top-level nodes carry data-category-id so the media form can show only the picked category's tree. --}}
@foreach($nodes as $node)
    @if($node->children->isNotEmpty())
        <details class="tag-tree-node" open @if(($depth ?? 0) === 0) data-category-id="{{ $node->category_id }}" @endif>
            <summary>
                <label>
                    <input type="checkbox" name="tags[]" value="{{ $node->id }}" @checked($selectedTags->contains($node->id))>
                    <span>{{ $node->name }}</span>
                    <small>{{ number_format($node->count) }}</small>
                </label>
            </summary>
            <div class="tag-tree-children">
                @include('cms-admin::partials.tag-tree', ['nodes' => $node->children, 'selectedTags' => $selectedTags, 'depth' => ($depth ?? 0) + 1])
            </div>
        </details>
    @else
        <label class="tag-tree-leaf" @if(($depth ?? 0) === 0) data-category-id="{{ $node->category_id }}" @endif>
            <input type="checkbox" name="tags[]" value="{{ $node->id }}" @checked($selectedTags->contains($node->id))>
            <span>{{ $node->name }}</span>
            <small>{{ number_format($node->count) }}</small>
        </label>
    @endif
@endforeach
