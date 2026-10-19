<?php

namespace Src\Catalog\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    protected $fillable = ['parent_id', 'category_id', 'name', 'slug', 'image', 'image_disk'];

    /** `image` stores a path on the disk named in `image_disk` (null = local public) or a plain external URL — resolve either to something an <img> can use. */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            if (blank($this->image)) {
                return null;
            }

            return str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')
                ? $this->image
                : MediaStorage::url($this->image, $this->image_disk);
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** The category this tag sits under — the top-level tag's own, which every tag below it follows. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function mediaContents(): BelongsToMany
    {
        return $this->belongsToMany(MediaContent::class);
    }

    /**
     * All tags in depth-first, parent-before-children order, each annotated
     * with its depth — enough to render an indented tree from a flat list.
     */
    public static function flattenedTree(): \Illuminate\Support\Collection
    {
        $byParent = static::orderBy('name')->get(['id', 'parent_id', 'category_id', 'name'])->groupBy('parent_id');

        $flatten = function (?int $parentId, int $depth) use (&$flatten, $byParent): \Illuminate\Support\Collection {
            return $byParent->get($parentId, collect())->flatMap(
                fn (self $tag) => collect([(object) ['id' => $tag->id, 'category_id' => $tag->category_id, 'name' => $tag->name, 'depth' => $depth]])
                    ->merge($flatten($tag->id, $depth + 1))
            );
        };

        return $flatten(null, 0);
    }

    /** The same tree, but genuinely nested — each node carries its own `children` collection — for recursive rendering (e.g. a collapsible checkbox tree). */
    public static function tree(): \Illuminate\Support\Collection
    {
        $byParent = static::withCount('mediaContents')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'category_id', 'name', 'slug'])
            ->groupBy('parent_id');

        $build = function (?int $parentId) use (&$build, $byParent): \Illuminate\Support\Collection {
            return $byParent->get($parentId, collect())->map(fn (self $tag) => (object) [
                'id' => $tag->id,
                'category_id' => $tag->category_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'count' => $tag->media_contents_count,
                'children' => $build($tag->id),
            ]);
        };

        return $build(null);
    }

    /** Hands this tag's category down to every tag nested under it (after its category or its parent changed). */
    public function cascadeCategory(): void
    {
        $this->unsetRelation('children');
        $ids = $this->descendantIds();

        if ($ids !== []) {
            static::whereIn('id', $ids)->update(['category_id' => $this->category_id]);
        }
    }

    /** [$ids] plus every tag nested under any of them, in one query — for filters that take several tags at once. */
    public static function idsWithDescendants(array $ids): array
    {
        $childrenOf = static::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $found = [];
        $pending = array_map('intval', $ids);

        while ($pending) {
            $id = array_pop($pending);
            if (isset($found[$id])) {
                continue;
            }
            $found[$id] = $id;
            foreach ($childrenOf->get($id, []) as $child) {
                $pending[] = $child->id;
            }
        }

        return array_values($found);
    }

    /** IDs of every tag nested under this one, so a tag can't become its own descendant's child. */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = [...$ids, ...$child->descendantIds()];
        }

        return $ids;
    }
}
