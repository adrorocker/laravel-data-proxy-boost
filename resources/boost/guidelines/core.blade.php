## Laravel Data Proxy

Laravel Data Proxy provides GraphQL-like declarative data retrieval with automatic query batching.

### When to Use

- Multiple related datasets needed in one request
- Dashboard/analytics pages with aggregates
- APIs combining data from multiple models
- Any scenario with 3+ related queries

### Core Concepts

- **Requirements**: Define what data you need
- **Shape**: Define structure (fields, relations, constraints)
- **Result**: Access resolved data
- **DataSet**: Lazy, memory-efficient collections

### File Structure Convention

When creating data classes, suggest `app/Data/` as the default location. If no clear directory exists, ask the user where they'd like to place data classes.

```text
app/
└── Data/              # Suggested default
    ├── DashboardData.php
    ├── UserProfileData.php
    └── OrderSummaryData.php
```

> **Note**: Always ask the user for their preferred directory if the project doesn't have an established pattern.

### Data Class Pattern

@verbatim
<code-snippet name="Basic data class pattern" lang="php">
namespace App\Data;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use AdroSoftware\DataProxy\Result;

class DashboardData
{
    public static function fetch(int $userId): Result
    {
        return DataProxy::make()->fetch(
            Requirements::make()
                ->one('user', User::class, $userId, self::userShape())
                ->query('posts', Post::class, self::postsShape($userId))
                ->count('totalPosts', Post::class,
                    Shape::make()->where('user_id', $userId))
        );
    }

    private static function userShape(): Shape
    {
        return Shape::make()
            ->select('id', 'name', 'email', 'avatar')
            ->with('profile');
    }

    private static function postsShape(int $userId): Shape
    {
        return Shape::make()
            ->select('id', 'title', 'excerpt', 'created_at')
            ->where('user_id', $userId)
            ->latest()
            ->limit(5);
    }
}
</code-snippet>
@endverbatim

### Custom Query Scopes

Use `scope()` to apply custom query modifications. Multiple scopes accumulate and are applied in order:

@verbatim
<code-snippet name="Multiple scopes pattern" lang="php">
Shape::make()
    // First scope - add aggregates
    ->scope(fn($query) => $query->withCount('likes'))

    // Second scope - add visibility constraints
    ->scope(fn($query, $resolved) => $query->whereIn('author_id', $resolved['followedIds']))

    // Conditional scope using when()
    ->when($excludeIds, fn($shape) => $shape->scope(
        fn($query) => $query->whereNotIn('id', $excludeIds)
    ))
</code-snippet>
@endverbatim

**Key points:**
- Scopes receive `($query, $resolved)` where `$resolved` contains all previously resolved requirements
- Multiple `scope()` calls accumulate (they don't overwrite each other)
- Use `getScopes()` to inspect all scopes, `clearScopes()` to remove them

### Best Practices

- Create dedicated data classes in `app/Data/`
- Use static methods for reusable shapes
- Leverage `compute()` for derived values
- Use `cache()` for expensive queries
- Return `Result` from data classes
- Use multiple `scope()` calls for composable query logic

### Hydration (Post-Query Batch Loading)

Use `hydrate()` to batch-load additional data after pagination queries execute. This is useful for loading data that can't be eager-loaded (external APIs, complex aggregates, cross-database data).

@verbatim
<code-snippet name="Hydration pattern" lang="php">
// In your Shape definition
Shape::make()
    ->select('id', 'title', 'author_id')
    ->hydrate(function ($items, $resolved) {
        // $items = Collection of paginated results
        // $resolved = all resolved requirements

        // Batch load external data
        $authorIds = $items->pluck('author_id')->unique();
        $avatars = ExternalAvatarService::batchGet($authorIds);

        // Mutate items in place
        $items->each(fn ($item) => $item->avatar_url = $avatars[$item->author_id] ?? null);
    });
</code-snippet>
@endverbatim

**When to use hydrate:**
- Loading data from external APIs (batch API calls)
- Cross-database lookups
- Complex aggregates not supported by eager loading
- Data that depends on the paginated result set

**When NOT to use hydrate:**
- Simple relation loading (use `with()` instead)
- Data available via eager loading
- Single-record lookups (no batching benefit)

### Deferred Values (Closures Only)

When a constraint value depends on data resolved earlier in the same request, pass a `Closure` (or invokable object). **Plain strings or `[Class, 'method']` arrays are not invoked** — they are treated as literal constraint values. This avoids accidental invocation of helper functions like `old()`, `auth()`, or `request()` whose names happen to coincide with a string constraint value.

@verbatim
<code-snippet name="Deferred constraint values" lang="php">
// ✅ Closure — invoked once with the current $resolved state
Shape::make()->where('age', '>=', fn($resolved) => $resolved['minAge']);

// ✅ Literal value
Shape::make()->where('status', 'old');

// ❌ Don't pass a string function name expecting it to fire
Shape::make()->where('column', 'auth'); // 'auth' is now treated as a literal value
</code-snippet>
@endverbatim

The same applies to entity IDs passed to `->one()` / `->many()`. Closures are invoked **exactly once per resolve** — don't depend on observable double-invocation.

### Aggregates Batch Automatically by Constraint

Multiple aggregates of the same model with the same `where` constraints collapse into a single SQL `SELECT` automatically. As of 0.5.0, aggregates with **different** constraints are also grouped — one query per constraint signature instead of one query per aggregate. No user action required, but it's worth knowing when designing dashboards:

@verbatim
<code-snippet name="Aggregate grouping" lang="php">
Requirements::make()
    // These two share constraints → 1 query
    ->count('total', User::class)
    ->sum('totalAge', User::class, 'age')

    // These two share different constraints → 1 query
    ->count('adults', User::class, Shape::make()->where('age', '>=', 18))
    ->sum('adultAge', User::class, 'age', Shape::make()->where('age', '>=', 18));

// Total: 2 queries (was 4 before 0.5.0)
</code-snippet>
@endverbatim

### Eager-Load Merge (Opt-In)

When two batched entity lookups (`one()` / `many()`) for the same model request the same relation with **different** shapes, the default behavior is last-write-wins — the second alias overwrites the first. Set the config flag to merge them instead (union of fields and constraints, max of limits):

```php
// config/dataproxy.php
'query' => [
    'merge_shared_eager_loads' => true,
],
```

Or via env: `DATAPROXY_MERGE_SHARED_EAGER_LOADS=true`. Default is `false` to preserve historical behavior.

### Anti-Patterns

- Don't use DataProxy for single simple queries
- Don't fetch data you won't use
- Don't skip field selection on large tables
- Don't nest relations beyond 3 levels without consideration
- Don't pass plain string function names as constraint values expecting them to be invoked — use a `Closure`
