<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Src\Catalog\Jobs\ProcessMediaContentAudio;
use Src\Catalog\Models\Artist;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Program;
use Src\Catalog\Models\Tag;
use Src\Catalog\Services\ImageVariantProcessor;
use Src\CmsAdmin\Http\Controllers\Concerns\GeneratesUniqueSlug;
use Src\CmsAdmin\Http\Controllers\Concerns\ResolvesUploadedMedia;
use Src\Notification\Notifications\MediaContentPublished;

class MediaContentController extends Controller
{
    use GeneratesUniqueSlug, ResolvesUploadedMedia;

    private const SOURCE_DIRECTORY = 'media-contents/uploads';

    /** Largest box a thumbnail is scaled down to fit — it keeps its own aspect ratio, never cropped. */
    private const THUMBNAIL_MAX_SIZE = 1280;

    private const AMBIENT_SLUG = 'ambient-music';

    public function __construct(private readonly ImageVariantProcessor $imageProcessor) {}

    public function index(): View
    {
        $mediaContents = MediaContent::with(['category', 'program', 'artist', 'author'])->latest()->paginate(15);

        return view('cms-admin::media-contents.index', compact('mediaContents'));
    }

    public function create(Request $request): View
    {
        $options = $this->formOptions();

        return view('cms-admin::media-contents.create', [
            ...$options,
            'podcast' => $this->podcastFrom($request),
            'fromMusic' => $this->fromMusic($request),
            // The category picked in the Music page's "New +" menu.
            'defaultCategoryId' => $options['categories']->firstWhere('slug', $request->query('category'))?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMediaContent($request);
        $data = $this->applyPublishState($request, $data);
        $data['author_id'] = $request->user('admin')->id;

        $mediaContent = MediaContent::create($data);
        $mediaContent->tags()->sync($this->tagIdsFor($request, $mediaContent->category_id));
        $processing = $this->applyStreamInput($request, $mediaContent);

        if ($mediaContent->status === 'public') {
            $request->user('admin')->notify(new MediaContentPublished($mediaContent->title, $mediaContent->media_type));
        }

        return $this->redirectAfterSave(
            $request,
            $processing ? 'Media content created. Audio conversion is processing in the background.' : 'Media content created.',
        );
    }

    public function edit(Request $request, MediaContent $mediaContent): View
    {
        $mediaContent->loadMissing('tags', 'artist', 'author');

        return view('cms-admin::media-contents.edit', [
            'mediaContent' => $mediaContent,
            ...$this->formOptions($mediaContent),
            'podcast' => $this->podcastFrom($request),
            'fromMusic' => $this->fromMusic($request),
        ]);
    }

    public function update(Request $request, MediaContent $mediaContent): RedirectResponse
    {
        $wasPublished = $mediaContent->status === 'public';
        $data = $this->validateMediaContent($request, $mediaContent);
        $data = $this->applyPublishState($request, $data, $mediaContent);

        $mediaContent->update($data);
        $mediaContent->tags()->sync($this->tagIdsFor($request, $mediaContent->category_id));
        $processing = $this->applyStreamInput($request, $mediaContent);

        if (! $wasPublished && $mediaContent->fresh()->status === 'public') {
            $request->user('admin')->notify(new MediaContentPublished($mediaContent->title, $mediaContent->media_type));
        }

        return $this->redirectAfterSave(
            $request,
            $processing ? 'Media content updated. Audio conversion is processing in the background.' : 'Media content updated.',
        );
    }

    public function destroy(Request $request, MediaContent $mediaContent): RedirectResponse
    {
        $this->deleteStoredMedia($mediaContent->stream);
        $this->deleteStoredMedia($mediaContent->source_path);
        $this->deleteStoredMedia($mediaContent->thumbnail);
        $mediaContent->delete();

        return $this->redirectAfterSave($request, 'Media content deleted.');
    }

    /**
     * The podcast whose page the admin came from ("Add episode" / "Edit" there
     * carry `?podcast=`, and the form posts it back): the form starts on that
     * show and saving returns to its page rather than the media list.
     */
    private function podcastFrom(Request $request): ?Program
    {
        return $request->filled('podcast') ? Program::find($request->integer('podcast')) : null;
    }

    /** Whether the admin came from the Music page (its links carry `from=music`, and the form posts it back). */
    private function fromMusic(Request $request): bool
    {
        return $request->input('from') === 'music';
    }

    private function redirectAfterSave(Request $request, string $status): RedirectResponse
    {
        if ($this->fromMusic($request)) {
            return redirect()->route('admin.music.index')->with('status', $status);
        }

        $podcast = $this->podcastFrom($request);

        return ($podcast
            ? redirect()->route('admin.programs.edit', $podcast)
            : redirect()->route('admin.media-contents.index'))->with('status', $status);
    }

    /**
     * The ticked tags that sit under the category (their `category_id`), or under Ambient Music —
     * whose moods any content can carry — or under no category at all; the form only offers those,
     * this keeps a stale or hand-made request from saving others. A category with no tags of its own
     * doesn't limit its tags.
     *
     * @return array<int, int>
     */
    private function tagIdsFor(Request $request, int $categoryId): array
    {
        $tagIds = array_map('intval', $request->input('tags', []));
        $own = Tag::where('category_id', $categoryId)->pluck('id')->all();

        if ($own === []) {
            return $tagIds;
        }

        $ambientCategoryId = $this->ambientCategoryId();
        $allowed = [
            ...$own,
            ...($ambientCategoryId ? Tag::where('category_id', $ambientCategoryId)->pluck('id')->all() : []),
            ...Tag::whereNull('category_id')->pluck('id')->all(),
        ];

        return array_values(array_intersect($tagIds, $allowed));
    }

    /**
     * Ambient Music is picked with the moods card rather than as a category, so the Category select
     * leaves it out — unless the item being edited is already filed under it.
     */
    private function formOptions(?MediaContent $editing = null): array
    {
        return [
            'categories' => Category::orderBy('sort_order')->get()
                ->reject(fn (Category $category) => $category->slug === self::AMBIENT_SLUG && $editing?->category_id !== $category->id)
                ->values(),
            'programs' => Program::with('category')->orderBy('name')->get(),
            'artists' => Artist::orderBy('name')->get(),
            'tagTree' => Tag::tree(),
            'ambientCategoryId' => $ambientCategoryId = $this->ambientCategoryId(),
            // The moods of the Ambient Music card: the tags directly under that category's top-level tag.
            'ambientTags' => $ambientCategoryId
                ? Tag::where('category_id', $ambientCategoryId)->whereHas('parent', fn ($query) => $query->whereNull('parent_id'))->orderBy('id')->get()
                : collect(),
        ];
    }

    private function ambientCategoryId(): ?int
    {
        return Category::where('slug', self::AMBIENT_SLUG)->value('id');
    }

    private function validateMediaContent(Request $request, ?MediaContent $mediaContent = null): array
    {
        // Empty selects/number inputs post as '' — `nullable` alone doesn't
        // coerce that to null, so normalize before validating.
        $request->merge([
            // Left empty, the slug comes from the title (an existing one is kept).
            'slug' => $request->filled('slug')
                ? $request->input('slug')
                : ($mediaContent?->slug ?? $this->uniqueSlug((string) $request->input('title'), 'media_contents', 'media')),
            'program_id' => $request->filled('program_id') ? (int) $request->input('program_id') : null,
            'artist_id' => $request->filled('artist_id') ? (int) $request->input('artist_id') : null,
            'duration_seconds' => $request->filled('duration_seconds') ? (int) $request->input('duration_seconds') : null,
        ]);

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('media_contents', 'slug')->ignore($mediaContent)],
            'artist_id' => ['nullable', 'integer', 'exists:artists,id'],
            'description' => ['nullable', 'string'],
            'media_type' => ['required', Rule::in(['audio', 'video', 'article'])],
            'stream' => ['nullable', 'file', 'mimes:mp3,wav,ogg,m4a,mp4,webm,mov', 'max:102400'],
            'stream_url' => ['nullable', 'string', 'max:2048', 'url'],
            'remove_stream' => ['nullable', 'boolean'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048', 'url'],
            'remove_thumbnail' => ['nullable', 'boolean'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['private', 'public', 'scheduled'])],
            'published_at' => [
                Rule::requiredIf(fn () => $request->input('status') === 'scheduled'),
                'nullable', 'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->input('status') === 'scheduled' && $value && $this->parseAdminDateTime($value)->isPast()) {
                        $fail('Scheduled content needs a publish date in the future.');
                    }
                },
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]);

        unset($data['tags'], $data['status'], $data['published_at']);

        $data['thumbnail'] = $this->resolveImageField(
            $this->imageProcessor, $request, 'thumbnail', $mediaContent?->thumbnail,
            config('services.media_storage.image_directory'), self::THUMBNAIL_MAX_SIZE, self::THUMBNAIL_MAX_SIZE,
            crop: false,
        );

        unset($data['stream'], $data['stream_url'], $data['remove_stream'], $data['thumbnail_url'], $data['remove_thumbnail']);

        return $data;
    }

    /**
     * Persist uploads quickly and leave CPU-heavy transcoding to the queue.
     * Existing playable audio stays in place until its replacement is ready.
     */
    private function applyStreamInput(Request $request, MediaContent $mediaContent): bool
    {
        if ($request->hasFile('stream')) {
            $sourcePath = $request->file('stream')->store(self::SOURCE_DIRECTORY, 'public');

            if (! $sourcePath) {
                throw new \RuntimeException('Unable to store the uploaded media file.');
            }

            $this->deleteStoredMedia($mediaContent->source_path);
            $mediaContent->update([
                'source_path' => $sourcePath,
                'processing_status' => 'processing',
                'processing_error' => null,
            ]);

            ProcessMediaContentAudio::dispatch($mediaContent->id, $sourcePath)->afterCommit();

            return true;
        }

        if ($request->filled('stream_url')) {
            $this->deleteStoredMedia($mediaContent->stream);
            $this->deleteStoredMedia($mediaContent->source_path);
            $mediaContent->update([
                'stream' => $request->input('stream_url'),
                'source_path' => null,
                'processing_status' => 'ready',
                'processing_error' => null,
            ]);

            return false;
        }

        if ($request->boolean('remove_stream')) {
            $this->deleteStoredMedia($mediaContent->stream);
            $this->deleteStoredMedia($mediaContent->source_path);
            $mediaContent->update([
                'stream' => null,
                'source_path' => null,
                'processing_status' => 'ready',
                'processing_error' => null,
            ]);
        }

        return false;
    }

    /**
     * Translates the admin's explicit choice (Private / Public / Scheduled)
     * into `is_published` + `published_at`. Scheduling requires a future
     * date (enforced in validation); `status` then reports 'scheduled' until
     * that time passes, with nothing else needing to run for it to "become"
     * public. Going private keeps the publish history rather than clearing it.
     */
    private function applyPublishState(Request $request, array $data, ?MediaContent $mediaContent = null): array
    {
        $status = $request->input('status', 'private');

        if ($status === 'private') {
            $data['is_published'] = false;
            $data['published_at'] = $mediaContent?->published_at;

            return $data;
        }

        if ($status === 'scheduled') {
            $data['is_published'] = true;
            $data['published_at'] = $this->parseAdminDateTime($request->input('published_at'));

            return $data;
        }

        // Public: keep a genuinely past published_at (preserves "published X ago"),
        // otherwise stamp it as live now — including when it was previously scheduled
        // for the future, since choosing Public here means "live immediately".
        $data['is_published'] = true;
        $data['published_at'] = $mediaContent?->published_at?->isPast()
            ? $mediaContent->published_at
            : now();

        return $data;
    }

    /**
     * The "Publish at" field is a datetime-local input, so its value has no
     * timezone of its own — it's whatever wall-clock time the admin typed,
     * in their own timezone. Interpret it as such before storing/comparing,
     * rather than assuming it's already in app.timezone (UTC).
     */
    private function parseAdminDateTime(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value, config('app.admin_timezone'))->setTimezone(config('app.timezone'));
    }
}
