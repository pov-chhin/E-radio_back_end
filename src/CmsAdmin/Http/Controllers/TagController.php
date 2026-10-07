<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\Tag;

class TagController extends Controller
{
    public function index(): View
    {
        $tree = Tag::flattenedTree();

        return view('cms-admin::tags.index', ['tags' => $tree, 'categoryNames' => Category::pluck('name', 'id')]);
    }

    public function create(): View
    {
        return view('cms-admin::tags.create', [
            'parentOptions' => Tag::flattenedTree(),
            'categories' => Category::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTag($request);

        $tag = Tag::create($data);
        $tag->cascadeCategory();

        return redirect()->route('admin.tags.index')->with('status', __('Tag created.'));
    }

    public function edit(Tag $tag): View
    {
        $excluded = [$tag->id, ...$tag->descendantIds()];
        $parentOptions = Tag::flattenedTree()->reject(fn ($node) => in_array($node->id, $excluded, true));

        $categories = Category::orderBy('sort_order')->get();

        return view('cms-admin::tags.edit', compact('tag', 'parentOptions', 'categories'));
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $data = $this->validateTag($request, $tag);

        $tag->update($data);
        $tag->cascadeCategory();

        return redirect()->route('admin.tags.index')->with('status', __('Tag updated.'));
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return redirect()->route('admin.tags.index')->with('status', __('Tag deleted, along with any child tags.'));
    }

    private function validateTag(Request $request, ?Tag $tag = null): array
    {
        // The "no parent" option posts an empty string; `nullable` alone
        // doesn't coerce that to null, so normalize it before validating.
        $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;
        $request->merge([
            'parent_id' => $parentId,
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
        ]);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:tags,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('tags', 'slug')->where(fn ($query) => $query->where('parent_id', $parentId))->ignore($tag),
            ],
        ]);

        // A sub tag is under whatever category its parent is; only a top-level tag picks its own.
        if ($parentId !== null) {
            $data['category_id'] = Tag::whereKey($parentId)->value('category_id');
        }

        if ($tag && $parentId !== null) {
            $tag->load('children');
            abort_if(in_array($parentId, [$tag->id, ...$tag->descendantIds()], true), 422, __('A tag cannot be its own parent.'));
        }

        return $data;
    }
}
