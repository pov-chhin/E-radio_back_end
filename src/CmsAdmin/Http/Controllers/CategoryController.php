<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Src\Catalog\Models\Category;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::withCount(['programs', 'mediaContents'])->orderBy('sort_order')->paginate(15);

        return view('cms-admin::categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('cms-admin::categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);

        Category::create($data);

        return redirect()->route('admin.categories.index')->with('status', __('Category created.'));
    }

    public function edit(Category $category): View
    {
        return view('cms-admin::categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validateCategory($request, $category);

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('status', __('Category updated.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', __('Category deleted, along with its podcasts and media content.'));
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
