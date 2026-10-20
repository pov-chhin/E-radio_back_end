<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Src\Catalog\Jobs\FetchYoutubeAudio;
use Src\Catalog\Jobs\ProcessMediaContentAudio;
use Src\Catalog\Models\Artist;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Program;
use Src\Catalog\Models\Tag;
use Src\Catalog\Services\ImageVariantProcessor;
use Src\Catalog\Services\YoutubeAudioFetcher;
use Src\Catalog\Services\YoutubeVideoDetails;
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

    /** The form the bare create address redirects to. */
    private const DEFAULT_FORM = 'song';

    /**
     * The form behind each "New …" button of the Article page, keyed by the category it starts on: the
     * categories the select offers (just one fixes it; null keeps them all), the fields left off, and — for
     * a podcast episode — `podcast_required`: the Podcast select has no "No podcast" to fall back on. The
     * `title_placeholder` is what the empty Title box says (translated by the form).
     */
    private const ARTICLE_FORMS = [
        'song' => ['categories' => ['song'], 'hide' => ['podcast', 'category'], 'title_placeholder' => 'Title Song'],
        'news' => ['categories' => ['news', 'health', 'economyfinance'], 'hide' => ['podcast', 'artist'], 'title_placeholder' => 'Title Audio'],
        'ambient-music' => ['categories' => ['ambient-music'], 'hide' => ['podcast', 'category'], 'title_placeholder' => 'Title Ambient'],
        'podcast' => ['categories' => ['podcast'], 'hide' => ['artist', 'category'], 'podcast_required' => true, 'title_placeholder' => 'Title Podcasts Eps'],
    ];

    public function __construct(private readonly ImageVariantProcessor $imageProcessor) {}

    public function index(): View
    {
        $mediaContents = MediaContent::with(['category', 'program', 'artists', 'author'])->latest()->paginate(15);

        return view('cms-admin::media-contents.index', compact('mediaContents'));
    }

    public function create(Request $request, ?string $category = null): View|RedirectResponse
    {
        // The bare address — no category, no podcast — is the Song form: /create/song.
        if ($category === null && ! $request->filled('category') && ! $request->filled('podcast')) {
            return redirect()->route('admin.media-contents.create', [...$request->query(), 'category' => self::DEFAULT_FORM]);
        }

        // The category comes in the path (/create/song); the older ?category=song still works.
        $requested = $category ?? $request->query('category');

        // A category in the path is the Article page's "New Song" / "New Program" / "New Podcast" / "New Ambient
        // Music" link, so it also means the form came from that page (back arrow, saving returns there); the
        // older ?category=…&from=music says so itself, and a bare ?category=… is the whole form.
        $fromMusic = $category !== null || $this->fromMusic($request);

        // Those buttons: fewer categories and fields (see ARTICLE_FORMS). "New Ambient Music" is the one place
        // Ambient Music is offered as a category.
        $articleForm = $fromMusic && is_string($requested) ? $this->articleFormFor($requested) : null;
        $options = $this->formOptions(withAmbient: in_array(self::AMBIENT_SLUG, $articleForm['categories'] ?? [], true));
        $defaultCategory = is_string($requested) ? $options['categories']->firstWhere('slug', $requested) : null;
        if ($articleForm['categories'] ?? null) {
            $options['categories'] = $options['categories']->whereIn('slug', $articleForm['categories'])->values();
        }

        // A new episode added from a podcast's page (?podcast=): it starts on that podcast and its category, which
        // the form fixes (the selects show them but can't change them), and has no Artist.
        $podcast = $this->podcastFrom($request);

        return view('cms-admin::media-contents.create', [
            ...$options,
            'podcast' => $podcast,
            // "New Song Media Content": the title names the category the form starts on (not for an episode of a podcast).
            'titleCategory' => $podcast ? null : $options['categories']->firstWhere('id', $defaultCategory?->id),
            'fromMusic' => $fromMusic,
            // The category picked in the Music page's "New +" menu.
            'defaultCategoryId' => $defaultCategory?->id,
            // A single category is fixed rather than offered.
            'lockedCategoryId' => $podcast
                ? $podcast->category_id
                : (($articleForm['categories'] ?? null) && $options['categories']->count() === 1 ? $options['categories']->first()->id : null),
            'lockedProgramId' => $podcast?->id,
            'hiddenFields' => [...($articleForm['hide'] ?? []), ...($podcast ? ['artist', 'category'] : [])],
            'podcastRequired' => $articleForm['podcast_required'] ?? false,
            'titlePlaceholder' => $articleForm['title_placeholder'] ?? null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMediaContent($request);
        $data = $this->applyPublishState($request, $data);
        $data['author_id'] = $request->user('admin')->id;

        $mediaContent = MediaContent::create($data);
        $mediaContent->artists()->sync($this->artistIdsFor($request));
        $mediaContent->tags()->sync($this->tagIdsFor($request, $mediaContent->category_id));
        $processing = $this->applyStreamInput($request, $mediaContent);

        if ($mediaContent->status === 'public') {
            $request->user('admin')->notify(new MediaContentPublished($mediaContent->title, $mediaContent->media_type));
        }

        return $this->redirectAfterSave(
            $request,
            $processing ? __('Media content created. Audio conversion is processing in the background.') : __('Media content created.'),
        );
    }

    public function edit(Request $request, MediaContent $mediaContent): View
    {
        return $this->editForm($request, $mediaContent, $this->fromMusic($request));
    }

    /**
     * The Article page's Edit link, /edit/news/16: the item's category and its id in the path. The path says it
     * came from the Article page (back arrow, saving returns there), so it needs no ?from=music. A category that
     * isn't the item's own goes to the right address.
     */
    public function editIn(Request $request, string $category, MediaContent $mediaContent): View|RedirectResponse
    {
        $mediaContent->loadMissing('category');

        if ($mediaContent->category->slug !== $category) {
            return redirect()->route('admin.media-contents.edit-in', [
                ...$request->query(),
                'category' => $mediaContent->category->slug,
                'media_content' => $mediaContent,
            ]);
        }

        return $this->editForm($request, $mediaContent, true);
    }

    /**
     * Editing from the Article page is the same form as its "New …" button for the item's category: the
     * categories offered (one fixes it), the fields left off, and the title naming the category.
     */
    private function editForm(Request $request, MediaContent $mediaContent, bool $fromMusic): View
    {
        $mediaContent->loadMissing('tags', 'artists', 'author', 'category');

        $articleForm = $fromMusic ? $this->articleFormFor($mediaContent->category->slug) : null;
        $options = $this->formOptions($mediaContent);
        if ($articleForm['categories'] ?? null) {
            $options['categories'] = $options['categories']->whereIn('slug', $articleForm['categories'])->values();
        }

        // An episode opened from its podcast's page (?podcast=): its category and podcast are fixed at what it
        // has now — the select shows them but can't change them — and the form has no Artist.
        $podcast = $this->podcastFrom($request);

        return view('cms-admin::media-contents.edit', [
            'mediaContent' => $mediaContent,
            ...$options,
            'podcast' => $podcast,
            'fromMusic' => $fromMusic,
            'titleCategory' => $fromMusic ? $mediaContent->category : null,
            'lockedCategoryId' => $podcast
                ? $mediaContent->category_id
                : (($articleForm['categories'] ?? null) && $options['categories']->count() === 1 ? $options['categories']->first()->id : null),
            'lockedProgramId' => $podcast ? ($mediaContent->program_id ?? $podcast->id) : null,
            'hiddenFields' => [...($articleForm['hide'] ?? []), ...($podcast ? ['artist', 'category'] : [])],
            'podcastRequired' => $articleForm['podcast_required'] ?? false,
            'titlePlaceholder' => $articleForm['title_placeholder'] ?? null,
        ]);
    }

    /** The Article page's form for a category: the entry keyed by it, or the one whose categories include it. */
    private function articleFormFor(string $slug): ?array
    {
        if (isset(self::ARTICLE_FORMS[$slug])) {
            return self::ARTICLE_FORMS[$slug];
        }

        foreach (self::ARTICLE_FORMS as $form) {
            if (in_array($slug, $form['categories'] ?? [], true)) {
                return $form;
            }
        }

        return null;
    }

    public function update(Request $request, MediaContent $mediaContent): RedirectResponse
    {
        $wasPublished = $mediaContent->status === 'public';
        $data = $this->validateMediaContent($request, $mediaContent);
        $data = $this->applyPublishState($request, $data, $mediaContent);

        // The Article page's form leaves some fields off (see ARTICLE_FORMS): a save from it leaves those as they
        // are instead of clearing them, so an item keeps a podcast or an artist the form doesn't show.
        $keepArtists = false;
        if ($this->fromMusic($request)) {
            $hidden = $this->articleFormFor((string) Category::whereKey($data['category_id'])->value('slug'))['hide'] ?? [];
            if (in_array('podcast', $hidden, true)) {
                unset($data['program_id']);
            }
            $keepArtists = in_array('artist', $hidden, true);
        }

        // An episode's form from its podcast's page has no Artist field either (see editForm): same rule.
        if ($this->podcastFrom($request)) {
            $keepArtists = true;
        }

        $mediaContent->update($data);
        if (! $keepArtists) {
            $mediaContent->artists()->sync($this->artistIdsFor($request));
        }
        $mediaContent->tags()->sync($this->tagIdsFor($request, $mediaContent->category_id, $mediaContent));
        $processing = $this->applyStreamInput($request, $mediaContent);

        if (! $wasPublished && $mediaContent->fresh()->status === 'public') {
            $request->user('admin')->notify(new MediaContentPublished($mediaContent->title, $mediaContent->media_type));
        }

        return $this->redirectAfterSave(
            $request,
            $processing ? __('Media content updated. Audio conversion is processing in the background.') : __('Media content updated.'),
        );
    }

    /**
     * "Retry" on a failed episode: runs the job that failed again. A YouTube link is fetched again; an upload is
     * converted again from its source file, which is kept until a conversion succeeds. Nothing else is retryable.
     */
    public function retryAudio(Request $request, MediaContent $mediaContent): RedirectResponse|JsonResponse
    {
        if ($mediaContent->processing_status !== 'failed') {
            return $this->retryResult($request, false, __('Nothing to retry — upload the audio again.'));
        }

        if ($mediaContent->source_url && YoutubeAudioFetcher::videoId((string) $mediaContent->source_url) !== null) {
            $mediaContent->update(['processing_status' => 'processing', 'processing_error' => null]);
            FetchYoutubeAudio::dispatch($mediaContent->id, $mediaContent->source_url)->afterCommit();

            return $this->retryResult($request, true, __('Trying again — the audio is being fetched.'));
        }

        if ($mediaContent->source_path && Storage::disk('public')->exists($mediaContent->source_path)) {
            $mediaContent->update(['processing_status' => 'processing', 'processing_error' => null]);
            ProcessMediaContentAudio::dispatch($mediaContent->id, $mediaContent->source_path)->afterCommit();

            return $this->retryResult($request, true, __('Trying again — the audio is being converted.'));
        }

        return $this->retryResult($request, false, __('Nothing to retry — upload the audio again.'));
    }

    /**
     * How the audio of one item is doing, for the lists and the edit page that watch a Processing row: they ask
     * every few seconds and change the row where it stands when it is Ready or Failed (see the layout's script).
     */
    public function processingStatus(MediaContent $mediaContent): JsonResponse
    {
        $failed = $mediaContent->processing_status === 'failed';

        return response()->json([
            'status' => $mediaContent->processing_status ?: 'ready',
            'error' => $failed && $mediaContent->processing_error ? Str::limit($mediaContent->processing_error, 300) : null,
        ]);
    }

    /**
     * The Retry buttons send their request from the page itself (fetch), so the list or form is not reloaded: they
     * ask for JSON and update the row in place. Without that (no JavaScript) it is a plain redirect with a message.
     */
    private function retryResult(Request $request, bool $started, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['started' => $started, 'message' => $message], $started ? 200 : 409);
        }

        return back()->with('status', $message);
    }

    /**
     * "Fill from YouTube" on the form: the title, length and cover of a pasted YouTube link, read from the YouTube
     * Data API. It only fills the form in — saving the link still fetches the audio in the background. The form is
     * used by whoever may create or edit media content, so either permission opens this.
     */
    public function youtubeDetails(Request $request, YoutubeVideoDetails $details): JsonResponse
    {
        $user = $request->user('admin');
        abort_unless($user && ($user->hasPermission('create-media-content') || $user->hasPermission('edit-media-content')), 403);

        $data = $request->validate(['url' => ['required', 'string', 'max:2048', 'url']]);

        try {
            return response()->json($details->fetch($data['url']));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, MediaContent $mediaContent): RedirectResponse
    {
        $this->deleteStoredMedia($mediaContent->stream, $mediaContent->stream_disk);
        $this->deleteStoredMedia($mediaContent->source_path);
        $this->deleteStoredMedia($mediaContent->thumbnail, $mediaContent->thumbnail_disk);
        $mediaContent->delete();

        return $this->redirectAfterSave($request, __('Media content deleted.'));
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

    /** The artists ticked on the form (validated: each exists, none twice). @return array<int, int> */
    private function artistIdsFor(Request $request): array
    {
        return array_values(array_map('intval', (array) $request->input('artist_ids', [])));
    }

    /**
     * The ticked tags that sit under the category (their `category_id`), or under Ambient Music —
     * whose moods any content can carry — or under no category at all; the form only offers those,
     * this keeps a stale or hand-made request from saving others. A category with no tags of its own
     * doesn't limit its tags.
     *
     * The form only lists the moods under the Ambient Music category, so saving an item filed elsewhere
     * (`$existing`, when editing) keeps the moods it already carries instead of dropping them unseen.
     *
     * @return array<int, int>
     */
    private function tagIdsFor(Request $request, int $categoryId, ?MediaContent $existing = null): array
    {
        $tagIds = array_map('intval', $request->input('tags', []));
        $own = Tag::where('category_id', $categoryId)->pluck('id')->all();
        $ambientCategoryId = $this->ambientCategoryId();

        if ($own !== []) {
            $allowed = [
                ...$own,
                ...($ambientCategoryId ? Tag::where('category_id', $ambientCategoryId)->pluck('id')->all() : []),
                ...Tag::whereNull('category_id')->pluck('id')->all(),
            ];

            $tagIds = array_values(array_intersect($tagIds, $allowed));
        }

        if ($existing && $ambientCategoryId && $categoryId !== $ambientCategoryId) {
            $moods = $existing->tags()->where('tags.category_id', $ambientCategoryId)->pluck('tags.id')->all();
            $tagIds = array_values(array_unique([...$tagIds, ...$moods]));
        }

        return $tagIds;
    }

    /**
     * Ambient Music is no ordinary category to pick, so the Category select leaves it out — unless the item
     * being edited is already filed under it, or the form is "New Ambient Music".
     */
    private function formOptions(?MediaContent $editing = null, bool $withAmbient = false): array
    {
        return [
            'categories' => Category::orderBy('sort_order')->get()
                ->reject(fn (Category $category) => $category->slug === self::AMBIENT_SLUG && ! $withAmbient && $editing?->category_id !== $category->id)
                ->values(),
            'programs' => Program::with('category')->orderBy('name')->get(),
            'artists' => Artist::orderBy('name')->get(),
            'tagTree' => Tag::tree(),
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
            'duration_seconds' => $request->filled('duration_seconds') ? (int) $request->input('duration_seconds') : null,
        ]);

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('media_contents', 'slug')->ignore($mediaContent)],
            'artist_ids' => ['nullable', 'array'],
            'artist_ids.*' => ['integer', 'distinct', 'exists:artists,id'],
            'description' => ['nullable', 'string'],
            'media_type' => ['required', Rule::in(['audio', 'video', 'article'])],
            'stream' => ['nullable', 'file', 'mimes:mp3,wav,ogg,m4a,mp4,webm,mov', 'max:102400'],
            'stream_url' => ['nullable', 'string', 'max:2048', 'url'],
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
                        $fail(__('Scheduled content needs a publish date in the future.'));
                    }
                },
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]);

        unset($data['tags'], $data['artist_ids'], $data['status'], $data['published_at']);

        $thumbnailReplaced = $request->hasFile('thumbnail') || $request->filled('thumbnail_url');

        $data['thumbnail'] = $this->resolveImageField(
            $this->imageProcessor, $request, 'thumbnail', $mediaContent?->thumbnail,
            config('services.media_storage.image_directory'), self::THUMBNAIL_MAX_SIZE, self::THUMBNAIL_MAX_SIZE,
            crop: false, disk: MediaStorage::current(), existingDisk: $mediaContent?->thumbnail_disk,
        );

        // The file's disk follows the file: a new thumbnail lands on the current storage, a removed one leaves no disk behind.
        if ($thumbnailReplaced) {
            $data['thumbnail_disk'] = MediaStorage::column();
        } elseif ($data['thumbnail'] === null) {
            $data['thumbnail_disk'] = null;
        }

        unset($data['stream'], $data['stream_url'], $data['thumbnail_url'], $data['remove_thumbnail']);

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
                throw new RuntimeException('Unable to store the uploaded media file.');
            }

            $this->deleteStoredMedia($mediaContent->source_path);
            $mediaContent->update([
                'source_path' => $sourcePath,
                'source_url' => null,
                'processing_status' => 'processing',
                'processing_error' => null,
            ]);

            ProcessMediaContentAudio::dispatch($mediaContent->id, $sourcePath)->afterCommit();

            return true;
        }

        if ($request->filled('stream_url')) {
            $url = trim((string) $request->input('stream_url'));

            // A YouTube link is not audio the app can play: its audio is fetched on the server and shrunk to a
            // small file in the background, like an upload. The current stream stays until the new one is ready.
            if (YoutubeAudioFetcher::videoId($url) !== null) {
                $this->deleteStoredMedia($mediaContent->source_path);
                $mediaContent->update([
                    'source_path' => null,
                    'source_url' => $url,
                    'processing_status' => 'processing',
                    'processing_error' => null,
                ]);

                FetchYoutubeAudio::dispatch($mediaContent->id, $url)->afterCommit();

                return true;
            }

            $this->deleteStoredMedia($mediaContent->stream, $mediaContent->stream_disk);
            $this->deleteStoredMedia($mediaContent->source_path);
            $mediaContent->update([
                'stream' => $request->input('stream_url'),
                'stream_disk' => null,
                'source_path' => null,
                'source_url' => null,
                'processing_status' => 'ready',
                'processing_error' => null,
            ]);

            return false;
        }

        // No new file or link: the current stream stays. It can only be replaced, never cleared.
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
