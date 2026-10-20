{{-- Recursive: $nodes is a Tag::tree() collection (or a node's ->children), $selectedTags is a collection of checked ids. Top-level nodes carry data-category-id so the media form can show only the picked category's tree; those of the categories in $foldRoots also carry data-fold-root (the form leaves their own row out), and the rows under those in $radioRoots are radio buttons. --}}
@foreach($nodes as $node)
    @if($node->children->isNotEmpty())
        <details class="tag-tree-node" open @if(($depth ?? 0) === 0) data-category-id="{{ $node->category_id }}" @if(in_array($node->category_id, $foldRoots ?? [], true)) data-fold-root @endif @endif>
            <summary>
                <label>
                    <input type="checkbox" name="tags[]" value="{{ $node->id }}" @checked($selectedTags->contains($node->id))>
                    <span>{{ $node->name }}</span>
                    <small>{{ number_format($node->count) }}</small>
                </label>
            </summary>
            <div class="tag-tree-children">
                {{-- The rows under a top row listed in $radioRoots (Economy/Finance: International, Local) are radio buttons: one at a time. --}}
                @include('cms-admin::partials.tag-tree', ['nodes' => $node->children, 'selectedTags' => $selectedTags, 'depth' => ($depth ?? 0) + 1, 'radios' => ($depth ?? 0) === 0 && in_array($node->category_id, $radioRoots ?? [], true)])
            </div>
        </details>
    @else
        <label class="tag-tree-leaf" @if(($depth ?? 0) === 0) data-category-id="{{ $node->category_id }}" @endif>
            <input type="{{ ($radios ?? false) ? 'radio' : 'checkbox' }}" name="tags[]" value="{{ $node->id }}" @if($radios ?? false) style="accent-color:var(--pink)" @endif @checked($selectedTags->contains($node->id))>
            <span>{{ $node->name }}</span>
            <small>{{ number_format($node->count) }}</small>
        </label>
    @endif
@endforeach
