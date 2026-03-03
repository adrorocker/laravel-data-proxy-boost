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

### Best Practices

- Create dedicated data classes in `app/Data/`
- Use static methods for reusable shapes
- Leverage `compute()` for derived values
- Use `cache()` for expensive queries
- Return `Result` from data classes

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

### Anti-Patterns

- Don't use DataProxy for single simple queries
- Don't fetch data you won't use
- Don't skip field selection on large tables
- Don't nest relations beyond 3 levels without consideration
