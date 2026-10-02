<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Src\Social\Models\Comment;

/**
 * Comments on a station or media-content item, identified by the same
 * ('station'|'media', id) pair the Flutter app already uses for
 * NowPlayingItem ids — see the morph map in SocialServiceProvider.
 */
class CommentController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['station', 'media'])],
            'id' => ['required', 'integer'],
        ]);

        $comments = Comment::query()
            ->where('commentable_type', $data['type'])
            ->where('commentable_id', $data['id'])
            ->with('user')
            ->latest()
            ->paginate(20);

        return CommentResource::collection($comments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['station', 'media'])],
            'id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $modelClass = Relation::getMorphedModel($data['type']);
        abort_unless($modelClass::whereKey($data['id'])->exists(), 404);

        $comment = Comment::create([
            'commentable_type' => $data['type'],
            'commentable_id' => $data['id'],
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return new CommentResource($comment->load('user'));
    }

    public function destroy(Request $request, Comment $comment)
    {
        abort_unless($comment->user_id === $request->user()->id, 403);

        $comment->delete();

        return response()->noContent();
    }
}
