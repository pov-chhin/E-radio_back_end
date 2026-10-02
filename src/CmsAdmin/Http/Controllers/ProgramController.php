<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Src\CmsAdmin\Http\Controllers\Concerns\GeneratesUniqueSlug;
use Src\CmsAdmin\Http\Controllers\Concerns\ResolvesUploadedMedia;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\Program;
use Src\Catalog\Models\Tag;
use Src\Catalog\Services\ImageVariantProcessor;

class ProgramController extends Controller
{
    use GeneratesUniqueSlug, ResolvesUploadedMedia;

    private const COVER_DIRECTORY = 'programs/covers';

    private const COVER_WIDTH = 1200;

    private const COVER_HEIGHT = 400;

    public function __construct(private readonly ImageVariantProcessor $imageProcessor) {}

    public function index(Request $request): View
    {
        // The tabs above the list, one per podcast type (the tags under Podcast); an unknown or empty `type` means "All".
        $types = $this->podcastTypes();
        $activeType = $types->firstWhere('slug', $request->query('type'));

        $programs = Program::with(['category', 'type'])
            ->withCount(['mediaContents', 'episodes'])
            ->when($activeType, fn ($query) => $query->whereIn('tag_id', Tag::idsWithDescendants([$activeType->id])))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('cms-admin::programs.index', compact('programs', 'types', 'activeType'));
    }

    public function create(Request $request): View
    {
        $types = $this->podcastTypes();

        return view('cms-admin::programs.create', [
            'types' => $types,
            // The one picked in the list's "New +" menu.
            'defaultTagId' => $types->firstWhere('slug', $request->query('type'))?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProgram($request);
        $data['is_published'] = $request->boolean('is_published');

        $program = Program::create($data);

        return redirect()->route('admin.programs.edit', $program)->with('status', 'Podcast created. Add its first episode below.');
    }

    public function edit(Program $program): View
    {
        return view('cms-admin::programs.edit', [
            'program' => $program,
            'types' => $this->podcastTypes(),
            'episodes' => $program->mediaContents()->with('artist')->latest()->latest('id')->get(),
        ]);
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $data = $this->validateProgram($request, $program);
        $data['is_published'] = $request->boolean('is_published');

        $program->update($data);

        return redirect()->route('admin.programs.index')->with('status', 'Podcast updated.');
    }

    public function destroy(Program $program): RedirectResponse
    {
        $this->deleteStoredMedia($program->cover_image);
        $program->delete();

        return redirect()->route('admin.programs.index')->with('status', 'Podcast deleted.');
    }

    private function podcastCategoryId(): ?int
    {
        return Category::where('slug', 'podcast')->value('id');
    }

    /**
     * The podcast types: the tags directly under the top-level tag of the Podcast category (Health,
     * Economy/Finance, Education, Politic, Civilization), in the order they were made.
     */
    private function podcastTypes(): Collection
    {
        return Tag::where('category_id', $this->podcastCategoryId())
            ->whereHas('parent', fn ($query) => $query->whereNull('parent_id'))
            ->orderBy('id')
            ->get();
    }

    private function validateProgram(Request $request, ?Program $program = null): array
    {
        // Left empty, the slug comes from the name (an existing one is kept); an empty type posts as ''.
        $request->merge([
            'slug' => $request->filled('slug')
                ? $request->input('slug')
                : ($program?->slug ?? $this->uniqueSlug((string) $request->input('name'), 'programs', 'podcast')),
            'tag_id' => $request->filled('tag_id') ? (int) $request->input('tag_id') : null,
        ]);

        $data = $request->validate([
            // A new program must have a type; an older one without a type can still be saved without.
            'tag_id' => [$program ? 'nullable' : 'required', 'integer', Rule::in($this->podcastTypes()->modelKeys())],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('programs', 'slug')->ignore($program)],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:8192'],
            'cover_image_url' => ['nullable', 'string', 'max:2048', 'url'],
            'remove_cover_image' => ['nullable', 'boolean'],
        ], [], ['tag_id' => 'podcast type']);

        // A program with a type is a Podcast; one still without a type keeps the category it has.
        if ($data['tag_id'] !== null) {
            $data['category_id'] = $this->podcastCategoryId();
        }

        $data['cover_image'] = $this->resolveImageField(
            $this->imageProcessor, $request, 'cover_image', $program?->cover_image,
            self::COVER_DIRECTORY, self::COVER_WIDTH, self::COVER_HEIGHT,
        );

        unset($data['cover_image_url'], $data['remove_cover_image']);

        return $data;
    }
}
