<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Src\Social\Models\Bookmark;

/**
 * A user's saved stations/media-content, distinct from the app's local-only
 * favorites. Identified by the same ('station'|'media', id) pair as
 * NowPlayingItem — see the morph map in SocialServiceProvider.
 */
class BookmarkController extends Controller
{
    /** All of the current user's bookmarks, as "type:id" strings the app can diff against locally. */
    public function index(Request $request)
    {
        $ids = $request->user()->bookmarks()
            ->get(['bookmarkable_type', 'bookmarkable_id'])
            ->map(fn (Bookmark $bookmark) => "{$bookmark->bookmarkable_type}:{$bookmark->bookmarkable_id}")
            ->values();

        return response()->json(['data' => $ids]);
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['station', 'media'])],
            'id' => ['required', 'integer'],
        ]);

        $modelClass = Relation::getMorphedModel($data['type']);
        abort_unless($modelClass::whereKey($data['id'])->exists(), 404);

        $bookmark = Bookmark::where('user_id', $request->user()->id)
            ->where('bookmarkable_type', $data['type'])
            ->where('bookmarkable_id', $data['id'])
            ->first();

        if ($bookmark) {
            $bookmark->delete();

            return response()->json(['bookmarked' => false]);
        }

        Bookmark::create([
            'user_id' => $request->user()->id,
            'bookmarkable_type' => $data['type'],
            'bookmarkable_id' => $data['id'],
        ]);

        return response()->json(['bookmarked' => true]);
    }
}
