<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Src\CmsAdmin\Http\Controllers\Concerns\ResolvesUploadedMedia;
use Src\Catalog\Services\ImageVariantProcessor;
use Src\Notification\Notifications\RadioChannelsSynced;
use Src\RadioSceaper\Models\RadioSceaperStation;
use Src\RadioSceaper\Services\RadioChannelSync;

class RadioChannelController extends Controller
{
    use ResolvesUploadedMedia;

    /** The longest side a cover is kept at; smaller pictures are left as they are. */
    private const COVER_MAX_SIZE = 1280;

    public function __construct(private readonly ImageVariantProcessor $imageProcessor) {}

    public function index(): View
    {
        $radioChannels = RadioSceaperStation::latest()->paginate(15);

        return view('cms-admin::radio-channels.index', compact('radioChannels'));
    }

    public function show(RadioSceaperStation $radioChannel): View
    {
        return view('cms-admin::radio-channels.show', compact('radioChannel'));
    }

    public function create(): View
    {
        return view('cms-admin::radio-channels.create');
    }

    public function store(Request $request): RedirectResponse
    {
        RadioSceaperStation::create($this->validateChannel($request));

        return redirect()->route('admin.radio-channels.index')->with('status', 'Channel added.');
    }

    public function edit(RadioSceaperStation $radioChannel): View
    {
        return view('cms-admin::radio-channels.edit', compact('radioChannel'));
    }

    public function update(Request $request, RadioSceaperStation $radioChannel): RedirectResponse
    {
        $radioChannel->update($this->validateChannel($request, $radioChannel));

        return redirect()->route('admin.radio-channels.index')->with('status', 'Channel updated.');
    }

    /**
     * Fetch working stations from radio-browser.info, add any that aren't
     * tracked yet, and refresh the stream URL for ones that are. Doesn't
     * publish anything — admins still review and publish manually. Mirrors
     * the `radio:sync` command.
     */
    public function sync(Request $request, RadioChannelSync $sync): RedirectResponse
    {
        $result = $sync->sync(config('services.radio_browser.country', 'KH'));

        if ($result === null) {
            return back()->with('error', 'radio-browser.info request failed.');
        }

        $request->user('admin')->notify(new RadioChannelsSynced($result['added'], $result['updated'], $result['stations']));

        return back()->with('status', "Synced — added {$result['added']}, updated {$result['updated']} (from {$result['stations']} station(s)).");
    }

    public function togglePublish(RadioSceaperStation $radioChannel): RedirectResponse
    {
        $radioChannel->update(['is_published' => ! $radioChannel->is_published]);

        return back()->with('status', 'Channel visibility updated.');
    }

    /** Opens the stream URL server-side (browsers can't reliably cross-origin probe arbitrary streams) and reports whether it's reachable. */
    public function checkSignal(RadioSceaperStation $radioChannel): RedirectResponse
    {
        if (blank($radioChannel->stream_url)) {
            return back()->with('error', "{$radioChannel->name} has no stream URL to check.");
        }

        $startedAt = microtime(true);

        try {
            $response = Http::withOptions(['stream' => true])->connectTimeout(4)->timeout(6)->get($radioChannel->stream_url);
            $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);

            if ($response->successful()) {
                return back()->with('status', "Signal check: {$radioChannel->name} is reachable (HTTP {$response->status()}, {$elapsedMs}ms).");
            }

            return back()->with('error', "Signal check: {$radioChannel->name} responded with HTTP {$response->status()}.");
        } catch (\Throwable $e) {
            return back()->with('error', "Signal check: {$radioChannel->name} is unreachable.");
        }
    }

    public function destroy(RadioSceaperStation $radioChannel): RedirectResponse
    {
        $this->deleteStoredMedia($radioChannel->cover, $radioChannel->cover_disk);
        $radioChannel->delete();

        return back()->with('status', 'Channel deleted.');
    }

    private function validateChannel(Request $request, ?RadioSceaperStation $radioChannel = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stream_url' => ['nullable', 'string', 'max:2048', 'url', Rule::unique('radio_sceaper_stations', 'stream_url')->ignore($radioChannel)],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:8192'],
            'cover_url' => ['nullable', 'string', 'max:2048', 'url'],
            'remove_cover' => ['nullable', 'boolean'],
            'bitrate' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            ...$data,
            ...$this->resolveStoredImage($this->imageProcessor, $request, 'cover', $radioChannel, self::COVER_MAX_SIZE, self::COVER_MAX_SIZE, crop: false),
        ];

        unset($data['cover_url']);

        return $data;
    }
}
