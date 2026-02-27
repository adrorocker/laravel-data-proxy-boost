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
