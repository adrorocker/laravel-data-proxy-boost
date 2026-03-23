---
name: data-proxy-infinite-scroll
description: Building infinite scroll feeds for blogs, timelines, and activity feeds.
---

# Infinite Scroll Feeds

## When to use this skill

Use when building feeds, timelines, blogs, or any content list with "load more" or infinite scroll.

## Steps to Build Infinite Scroll Feed

### Step 1: Create Feed Data Class

```php
namespace App\Data;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use AdroSoftware\DataProxy\Result;
use App\Models\Post;

class FeedData
{
    public static function fetch(
        int $limit = 20,
        ?int $afterId = null
    ): Result {
        return DataProxy::make()->fetch(
            Requirements::make()
                ->query('items', Post::class, self::shape($limit, $afterId))
                ->compute('hasMore', fn($data) => $data['items']->count() === $limit, ['items'])
                ->compute('nextCursor', fn($data) => $data['items']->last()?->id, ['items'])
        );
    }

    private static function shape(int $limit, ?int $afterId): Shape
    {
        return Shape::make()
            ->select('id', 'title', 'excerpt', 'created_at')
            ->with('author', Shape::make()->select('id', 'name', 'avatar'))
            ->when($afterId, fn($s) => $s->where('id', '<', $afterId))
            ->latest()
            ->limit($limit);
    }
}
```

### Step 2: Return JSON Response

```php
public function feed(Request $request)
{
    $data = FeedData::fetch(
        limit: $request->input('limit', 20),
        afterId: $request->input('after_id')
    );

    return response()->json([
        'items' => $data->items->toArray(),
        'hasMore' => $data->hasMore,
        'nextCursor' => $data->nextCursor,
    ]);
}
```

### Step 3: Frontend Load More

On button click or scroll:
- Call API with `?after_id={nextCursor}`
- Append new items to list
- Update cursor for next request
- Hide button if `hasMore` is false

## Ordering Consistency

Always use consistent ordering (e.g., `id DESC`) to prevent duplicates when new items are added.

## Composable Filtering with Multiple Scopes

Use multiple `scope()` calls for composable feed filtering. Scopes accumulate and are applied in order:

```php
private static function shape(
    int $limit,
    ?int $afterId,
    ?User $viewer,
    array $excludeIds = []
): Shape {
    return Shape::make()
        ->select('id', 'title', 'excerpt', 'created_at')
        ->with('author', Shape::make()->select('id', 'name', 'avatar'))
        // Base scope - aggregates
        ->scope(fn($query) => $query->withCount('likes'))
        // Visibility scope - filter based on viewer permissions
        ->scope(fn($query) => $query->listableFor($viewer))
        // Cursor-based pagination scope
        ->when($afterId, fn($s) => $s->scope(
            fn($query) => $query->where('id', '<', $afterId)
        ))
        // Exclusion scope - hide already-seen items
        ->when(!empty($excludeIds), fn($s) => $s->scope(
            fn($query) => $query->whereNotIn('id', $excludeIds)
        ))
        ->orderByDesc('id')
        ->limit($limit);
}
```

This pattern keeps each concern (aggregates, visibility, pagination, exclusions) in its own scope, making the code easier to understand and maintain.

## Timeline Feed Example

```php
class TimelineData
{
    public static function fetch(int $userId, int $limit = 20, ?int $afterId = null): Result
    {
        return DataProxy::make()->fetch(
            Requirements::make()
                ->query('activities', Activity::class, self::shape($userId, $limit, $afterId))
                ->compute('hasMore', fn($data) => $data['activities']->count() === $limit, ['activities'])
                ->compute('nextCursor', fn($data) => $data['activities']->last()?->id, ['activities'])
        );
    }

    private static function shape(int $userId, int $limit, ?int $afterId): Shape
    {
        return Shape::make()
            ->select('id', 'type', 'data', 'created_at')
            ->with('subject')
            ->where('user_id', $userId)
            ->when($afterId, fn($s) => $s->where('id', '<', $afterId))
            ->orderByDesc('id')
            ->limit($limit);
    }
}
```

## Livewire Integration

```php
class FeedComponent extends Component
{
    public int $cursor = 0;
    public bool $hasMore = true;
    public Collection $items;

    public function mount()
    {
        $this->items = collect();
        $this->loadMore();
    }

    public function loadMore()
    {
        $data = FeedData::fetch(
            limit: 20,
            afterId: $this->cursor ?: null
        );

        $this->items = $this->items->concat($data->items);
        $this->hasMore = $data->hasMore;
        $this->cursor = $data->nextCursor;
    }
}
```

## Batch Loading with Hydrate

Use `hydrate()` to batch-load additional data for feed items after the query executes. Ideal for like counts, user interactions, or external data.

### Loading Engagement Metrics

```php
private static function shape(int $limit, ?int $afterId): Shape
{
    return Shape::make()
        ->select('id', 'title', 'excerpt', 'created_at')
        ->with('author', Shape::make()->select('id', 'name', 'avatar'))
        ->when($afterId, fn($s) => $s->where('id', '<', $afterId))
        ->latest()
        ->limit($limit)
        ->hydrate(function ($items, $resolved) {
            // Batch load like counts
            $postIds = $items->pluck('id');
            $likeCounts = Like::whereIn('post_id', $postIds)
                ->groupBy('post_id')
                ->selectRaw('post_id, count(*) as count')
                ->pluck('count', 'post_id');

            $items->each(fn ($post) => $post->like_count = $likeCounts[$post->id] ?? 0);
        });
}
```

### Loading User Interactions

```php
->hydrate(function ($items, $resolved) {
    $currentUserId = auth()->id();
    $postIds = $items->pluck('id');

    // Batch check if current user liked each post
    $userLikes = Like::where('user_id', $currentUserId)
        ->whereIn('post_id', $postIds)
        ->pluck('post_id')
        ->flip();

    // Batch check bookmarks
    $userBookmarks = Bookmark::where('user_id', $currentUserId)
        ->whereIn('post_id', $postIds)
        ->pluck('post_id')
        ->flip();

    $items->each(function ($post) use ($userLikes, $userBookmarks) {
        $post->is_liked = isset($userLikes[$post->id]);
        $post->is_bookmarked = isset($userBookmarks[$post->id]);
    });
})
```

### Loading External API Data

```php
->hydrate(function ($items, $resolved) {
    // Batch fetch preview images from external service
    $urls = $items->pluck('external_url')->filter()->toArray();
    $previews = LinkPreviewService::batchFetch($urls);

    $items->each(function ($post) use ($previews) {
        if ($post->external_url) {
            $post->preview_image = $previews[$post->external_url] ?? null;
        }
    });
})
```
