---
name: data-proxy-web-views
description: Building data for Blade and Inertia views with Laravel Data Proxy.
---

# Building Data for Web Views

## When to use this skill

Use when fetching data for Blade templates or Inertia page components.

## Steps for Blade Views

### Step 1: Create Data Class

Create a data class in suggested `app/Data/` directory (or ask user).

### Step 2: Fetch in Controller

```php
$data = PageData::fetch($id);
return view('page', $data->all());
```

### Step 3: Access in Blade

```blade
{{ $user->name }}
@foreach($posts as $post)
    {{ $post->title }}
@endforeach
```

## Steps for Inertia Pages

### Step 1: Create Data Class

Same as Blade, but consider using `->asArray()` on shapes.

### Step 2: Return from Controller

```php
$data = PageData::fetch($id);
return Inertia::render('Page', $data->all());
```

### Step 3: Use in React/Vue

Access props directly: `props.user`, `props.posts`

## View-Specific Shape Tips

- Use `select()` to limit fields sent to frontend
- Use `compute()` for formatted/display values
- Use `asArray()` when you don't need model methods
- Use multiple `scope()` calls for composable query logic

## Using Multiple Scopes

Scopes accumulate, making them ideal for composable queries:

```php
private static function postsShape(?User $viewer, ?int $excludeId = null): Shape
{
    return Shape::make()
        ->select('id', 'title', 'excerpt', 'published_at')
        // Base scope - always applied
        ->scope(fn($query) => $query->withCount('likes'))
        // Visibility scope
        ->scope(fn($query) => $query->listableFor($viewer))
        // Conditional exclusion scope
        ->when($excludeId, fn($shape) => $shape->scope(
            fn($query) => $query->where('id', '!=', $excludeId)
        ))
        ->latest()
        ->limit(10);
}
```

## Complete Example

```php
namespace App\Data;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use AdroSoftware\DataProxy\Result;
use App\Models\Post;

class BlogPostData
{
    public static function fetch(int $postId): Result
    {
        return DataProxy::make()->fetch(
            Requirements::make()
                ->one('post', Post::class, $postId, self::postShape())
                ->query('relatedPosts', Post::class, self::relatedShape($postId))
                ->compute('readTime', fn($data) => ceil(str_word_count($data['post']->content) / 200), ['post'])
        );
    }

    private static function postShape(): Shape
    {
        return Shape::make()
            ->select('id', 'title', 'content', 'published_at')
            ->with('author', Shape::make()->select('id', 'name', 'avatar'));
    }

    private static function relatedShape(int $postId): Shape
    {
        return Shape::make()
            ->select('id', 'title', 'excerpt')
            ->where('id', '!=', $postId)
            ->latest()
            ->limit(3);
    }
}
```

## Controller Usage

```php
class BlogController extends Controller
{
    public function show(int $id)
    {
        $data = BlogPostData::fetch($id);

        return view('blog.show', $data->all());
    }
}
```
