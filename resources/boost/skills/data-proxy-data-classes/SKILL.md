---
name: data-proxy-data-classes
description: Creating reusable data classes with Laravel Data Proxy for clean, testable data fetching.
---

# Creating Reusable Data Classes

## When to use this skill

Use when organizing data fetching logic into dedicated classes for reusability and testing.

## Steps to Create a Data Class

### Step 1: Determine Location

Ask user for preferred directory. Suggest `app/Data/` if no pattern exists.

### Step 2: Create the Data Class

Create a class with:
- Static `fetch()` method that returns `Result`
- Private static methods for reusable shapes
- Clear parameter types

### Step 3: Define Requirements

Add requirements for all needed data:
- `one()` for single entities
- `many()` for entity collections
- `query()` for filtered/ordered results
- `count()`/`sum()` for aggregates
- `compute()` for derived values

### Step 4: Extract Reusable Shapes

Move repeated Shape configurations to static methods:
- `userShape()`, `postShape()`, etc.
- Accept parameters for dynamic constraints

### Step 5: Add to Controller/Action

Call `DataClass::fetch($params)` and pass `$result->all()` to view.

## Example Data Class

```php
namespace App\Data;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use AdroSoftware\DataProxy\Result;
use App\Models\User;

class UserProfileData
{
    public static function fetch(int $userId): Result
    {
        return DataProxy::make()->fetch(
            Requirements::make()
                ->one('user', User::class, $userId, self::shape())
        );
    }

    private static function shape(): Shape
    {
        return Shape::make()
            ->select('id', 'name', 'email', 'avatar')
            ->with('profile');
    }
}
```

## Testing Data Classes

```php
public function test_user_profile_data_fetches_correctly(): void
{
    $user = User::factory()->create();

    $result = UserProfileData::fetch($user->id);

    $this->assertEquals($user->id, $result->user->id);
    $this->assertEquals($user->name, $result->user->name);
}
```

## Composing Data Classes

Data classes can call other data classes for complex scenarios:

```php
class DashboardData
{
    public static function fetch(int $userId): Result
    {
        $profile = UserProfileData::fetch($userId);

        return DataProxy::make()->fetch(
            Requirements::make()
                ->value('user', $profile->user)
                ->query('recentPosts', Post::class, self::recentPostsShape($userId))
                ->count('totalPosts', Post::class, Shape::make()->where('user_id', $userId))
        );
    }
}
```
