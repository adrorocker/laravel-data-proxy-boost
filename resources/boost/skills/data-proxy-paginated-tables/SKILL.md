---
name: data-proxy-paginated-tables
description: Building paginated, sortable, filterable tables for SaaS admin panels.
---

# Paginated Tables for SaaS Apps

## When to use this skill

Use when building admin tables with pagination, sorting, filtering, and search.

## Steps to Build a Paginated Table

### Step 1: Create Table Data Class

```php
namespace App\Data;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use AdroSoftware\DataProxy\Result;
use App\Models\User;

class UsersTableData
{
    public static function fetch(
        int $perPage = 15,
        int $page = 1,
        ?string $search = null,
        string $sortBy = 'created_at',
        string $sortDir = 'desc'
    ): Result {
        return DataProxy::make()->fetch(
            Requirements::make()
                ->paginate('users', User::class, $perPage, $page,
                    self::shape($search, $sortBy, $sortDir))
                ->count('total', User::class, Shape::make())
        );
    }

    private static function shape(?string $search, string $sortBy, string $sortDir): Shape
    {
        return Shape::make()
            ->select('id', 'name', 'email', 'created_at')
            ->when($search, fn($s) => $s->where('name', 'like', "%{$search}%"))
            ->orderBy($sortBy, $sortDir);
    }
}
```

### Step 2: Use in Controller

```php
$data = UsersTableData::fetch(
    perPage: $request->input('per_page', 15),
    page: $request->input('page', 1),
    search: $request->input('search'),
    sortBy: $request->input('sort_by', 'created_at'),
    sortDir: $request->input('sort_dir', 'desc')
);
```

### Step 3: Access Pagination Info

```php
$data->users->currentPage()
$data->users->lastPage()
$data->users->total()
$data->users->hasMorePages()
```

## Adding Filters

```php
private static function shape(?string $search, ?string $status, array $roles): Shape
{
    return Shape::make()
        ->select('id', 'name', 'email', 'status', 'created_at')
        ->when($search, fn($s) => $s->where('name', 'like', "%{$search}%"))
        ->when($status, fn($s) => $s->where('status', $status))
        ->when(count($roles) > 0, fn($s) => $s->whereIn('role_id', $roles))
        ->orderBy('created_at', 'desc');
}
```

## Adding Table Stats

```php
Requirements::make()
    ->paginate('users', User::class, $perPage, $page, self::shape($search))
    ->count('total', User::class, Shape::make())
    ->count('activeCount', User::class, Shape::make()->where('status', 'active'))
    ->count('pendingCount', User::class, Shape::make()->where('status', 'pending'))
```

## Bulk Selection Support

```php
Requirements::make()
    ->paginate('users', User::class, $perPage, $page, self::shape($search))
    ->query('allIds', User::class,
        Shape::make()
            ->select('id')
            ->when($search, fn($s) => $s->where('name', 'like', "%{$search}%"))
    )
```

## Batch Loading with Hydrate

Use `hydrate()` to batch-load additional data after pagination executes. Perfect for external API calls, cross-database lookups, or complex aggregates.

### Loading Related Counts

```php
private static function shape(?string $search, string $sortBy, string $sortDir): Shape
{
    return Shape::make()
        ->select('id', 'name', 'email', 'created_at')
        ->when($search, fn($s) => $s->where('name', 'like', "%{$search}%"))
        ->orderBy($sortBy, $sortDir)
        ->hydrate(function ($items, $resolved) {
            // Batch load order counts
            $userIds = $items->pluck('id');
            $counts = Order::whereIn('user_id', $userIds)
                ->groupBy('user_id')
                ->selectRaw('user_id, count(*) as count')
                ->pluck('count', 'user_id');

            $items->each(fn ($user) => $user->order_count = $counts[$user->id] ?? 0);
        });
}
```

### Loading External Data

```php
->hydrate(function ($items, $resolved) {
    // Batch fetch from external service
    $emails = $items->pluck('email')->toArray();
    $gravatars = GravatarService::batchLookup($emails);

    $items->each(fn ($user) => $user->gravatar_url = $gravatars[$user->email] ?? null);
})
```

### Using Resolved Data

```php
->hydrate(function ($items, $resolved) {
    // Access other resolved requirements
    $settings = $resolved['companySettings'];

    $items->each(function ($user) use ($settings) {
        $user->display_name = $settings->show_full_name
            ? $user->name
            : $user->initials;
    });
})
```
