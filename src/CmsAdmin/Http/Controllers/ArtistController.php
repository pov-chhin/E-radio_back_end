<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Src\CmsAdmin\Http\Controllers\Concerns\ResolvesUploadedMedia;
use Src\Catalog\Models\Artist;
use Src\Catalog\Services\ImageVariantProcessor;

class ArtistController extends Controller
{
    use ResolvesUploadedMedia;

    public const STATUSES = ['active', 'inactive'];

    public const SOCIAL_PLATFORMS = ['facebook', 'instagram', 'tiktok', 'youtube'];

    /** [field => [directory, width, height]] — the exact variant every photo/URL for that slot gets cropped and converted to. */
    private const IMAGE_SPECS = [
        'photo' => ['artists/photos', 300, 300],
        'cover' => ['artists/covers', 1200, 400],
    ];

    public function __construct(private readonly ImageVariantProcessor $imageProcessor) {}

    public function index(): View
    {
        $artists = Artist::orderBy('name')->paginate(15);

        return view('cms-admin::artists.index', compact('artists'));
    }

    public function create(): View
    {
        return view('cms-admin::artists.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        Artist::create($this->validateArtist($request));

        return redirect()->route('admin.artists.index')->with('status', 'Artist added.');
    }

    public function edit(Artist $artist): View
    {
        return view('cms-admin::artists.edit', ['artist' => $artist, ...$this->formOptions()]);
    }

    public function update(Request $request, Artist $artist): RedirectResponse
    {
        $artist->update($this->validateArtist($request, $artist));

        return redirect()->route('admin.artists.index')->with('status', 'Artist updated.');
    }

    public function destroy(Artist $artist): RedirectResponse
    {
        $this->deleteStoredMedia($artist->photo);
        $this->deleteStoredMedia($artist->cover);
        $artist->delete();

        return redirect()->route('admin.artists.index')->with('status', 'Artist deleted.');
    }

    private function formOptions(): array
    {
        return ['statuses' => self::STATUSES, 'socialPlatforms' => self::SOCIAL_PLATFORMS];
    }

    private function validateArtist(Request $request, ?Artist $artist = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('artists', 'slug')->ignore($artist)],
            'stage_name' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'photo_url' => ['nullable', 'string', 'max:2048', 'url'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:8192'],
            'cover_url' => ['nullable', 'string', 'max:2048', 'url'],
            'remove_photo' => ['nullable', 'boolean'],
            'remove_cover' => ['nullable', 'boolean'],
            'bio' => ['nullable', 'string'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'string', 'max:2048', 'url'],
        ]);

        $data['verified'] = $request->boolean('verified');
        $data['social_links'] = array_filter($request->input('social_links', []), fn ($url) => filled($url));

        foreach (self::IMAGE_SPECS as $field => [$directory, $width, $height]) {
            $data[$field] = $this->resolveImageField($this->imageProcessor, $request, $field, $artist?->{$field}, $directory, $width, $height);
        }

        unset($data['photo_url'], $data['cover_url']);

        return $data;
    }
}
