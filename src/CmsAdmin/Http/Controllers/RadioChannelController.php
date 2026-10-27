<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
        // Every channel: the page's DataTable searches, sorts, filters and pages them in the browser.
        $radioChannels = RadioSceaperStation::inListenerOrder()->get();

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

        return redirect()->route('admin.radio-channels.index')->with('status', __('Channel added.'));
    }

    public function edit(RadioSceaperStation $radioChannel): View
    {
        return view('cms-admin::radio-channels.edit', compact('radioChannel'));
    }

    public function update(Request $request, RadioSceaperStation $radioChannel): RedirectResponse
    {
        $radioChannel->update($this->validateChannel($request, $radioChannel));

        return redirect()->route('admin.radio-channels.index')->with('status', __('Channel updated.'));
    }

    /**
     * A row dragged to a new place in the list: put `id` just before or after `target` in the order listeners see, then number the
     * whole list 1, 2, 3… so the place is kept (channels that were left at 0 are numbered too, in the order they were showing).
     */
    public function reorder(Request $request): Response
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:radio_sceaper_stations,id'],
            'target' => ['required', 'integer', 'different:id', 'exists:radio_sceaper_stations,id'],
            'placement' => ['required', Rule::in(['before', 'after'])],
        ]);

        $ids = RadioSceaperStation::inListenerOrder()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ids = array_values(array_diff($ids, [(int) $data['id']]));
        $at = array_search((int) $data['target'], $ids, true) + ($data['placement'] === 'after' ? 1 : 0);
        array_splice($ids, $at, 0, [(int) $data['id']]);

        // Only the rows whose number changes are written, a few hundred to a statement.
        $current = RadioSceaperStation::pluck('sort_order', 'id');
        $changes = [];
        foreach ($ids as $index => $id) {
            if ((int) $current[$id] !== $index + 1) {
                $changes[$id] = $index + 1;
            }
        }

        DB::transaction(function () use ($changes): void {
            foreach (array_chunk($changes, 500, true) as $chunk) {
                $cases = collect($chunk)->map(fn (int $sortOrder, int $id) => "WHEN {$id} THEN {$sortOrder}")->implode(' ');

                DB::table('radio_sceaper_stations')->whereIn('id', array_keys($chunk))->update(['sort_order' => DB::raw("CASE id {$cases} END")]);
            }
        });

        return response()->noContent();
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
            return back()->with('error', __('radio-browser.info request failed.'));
        }

        $request->user('admin')->notify(new RadioChannelsSynced($result['added'], $result['updated'], $result['stations']));

        return back()->with('status', __('Synced — added :added, updated :updated (from :stations station(s)).', [
            'added' => $result['added'],
            'updated' => $result['updated'],
            'stations' => $result['stations'],
        ]));
    }

    public function togglePublish(RadioSceaperStation $radioChannel): RedirectResponse
    {
        $radioChannel->update(['is_published' => ! $radioChannel->is_published]);

        return back()->with('status', __('Channel visibility updated.'));
    }

    /** Opens the stream URL server-side (browsers can't reliably cross-origin probe arbitrary streams) and reports whether it's reachable. */
    public function checkSignal(RadioSceaperStation $radioChannel): RedirectResponse
    {
        if (blank($radioChannel->stream_url)) {
            return back()->with('error', __(':name has no stream URL to check.', ['name' => $radioChannel->name]));
        }

        $startedAt = microtime(true);

        try {
            $response = Http::withOptions(['stream' => true])->connectTimeout(4)->timeout(6)->get($radioChannel->stream_url);
            $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);

            if ($response->successful()) {
                return back()->with('status', __('Signal check: :name is reachable (HTTP :status, :elapsed).', [
                    'name' => $radioChannel->name,
                    'status' => $response->status(),
                    'elapsed' => "{$elapsedMs}ms",
                ]));
            }

            return back()->with('error', __('Signal check: :name responded with HTTP :status.', ['name' => $radioChannel->name, 'status' => $response->status()]));
        } catch (\Throwable $e) {
            return back()->with('error', __('Signal check: :name is unreachable.', ['name' => $radioChannel->name]));
        }
    }

    public function destroy(RadioSceaperStation $radioChannel): RedirectResponse
    {
        $this->deleteStoredMedia($radioChannel->cover, $radioChannel->cover_disk);
        $radioChannel->delete();

        return back()->with('status', __('Channel deleted.'));
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
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        // A blank box means "no fixed position" (0), not NULL — the column can't hold one.
        if (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) $data['sort_order'];
        }

        $data = [
            ...$data,
            ...$this->resolveStoredImage($this->imageProcessor, $request, 'cover', $radioChannel, self::COVER_MAX_SIZE, self::COVER_MAX_SIZE, crop: false),
        ];

        unset($data['cover_url']);

        return $data;
    }
}
