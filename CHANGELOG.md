# Changelog

All notable changes to `laravel-data-proxy-boost` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Stability**: this companion package follows the
> [`adrosoftware/laravel-data-proxy`](https://github.com/adrorocker/laravel-data-proxy)
> pre-1.0 line and may include breaking changes between minor releases.

## [Unreleased]

## [0.5.0] - 2026-04-25

Aligns the guidelines and skills with `adrosoftware/laravel-data-proxy` 0.5.0.
This bump is a breaking change for downstream apps because the suggested
companion-package constraint moves from `^0.1` to `^0.5`.

### Added
- Core guideline: **Deferred Values (Closures Only)** — explains that as of
  data-proxy 0.5.0, only `Closure` instances and invokable objects are
  invoked against the resolution state. Plain string function names and
  `[Class, 'method']` arrays are now treated as literal constraint values.
- Core guideline: **Aggregates Batch Automatically by Constraint** — covers
  the new constraint-signature grouping that collapses mixed-constraint
  aggregates of the same model into one query per signature.
- Core guideline: **Eager-Load Merge (Opt-In)** — documents the new
  `query.merge_shared_eager_loads` config flag (default `false`) that
  unions fields, constraints and limits when batched aliases share a
  relation, instead of last-write-wins.
- Core guideline: note that callable IDs in `->one()` / `->many()` are now
  invoked **exactly once** per resolve.
- Paginated-tables skill: performance note explaining that table stats
  (`count` / `sum` per filter) are grouped by constraint signature.
- New anti-pattern entry: don't pass plain string function names as
  constraint values expecting them to be invoked.

### Changed
- **BREAKING**: bumped suggested companion package constraint from `^0.1`
  to `^0.5` in `composer.json`. Apps still on `adrosoftware/laravel-data-proxy`
  `0.4.x` or earlier should pin to this package's `^0.4` line.
- README: requirements now list Laravel 11, 12, or 13 (was 11.0 or 12.0)
  and call out PHP 8.3+ for Laravel 13. Added an explicit
  `adrosoftware/laravel-data-proxy ^0.5` requirement line.

### Upgrade guide (0.4.x → 0.5.0)

1. Upgrade `adrosoftware/laravel-data-proxy` to `^0.5` first — see that
   package's `CHANGELOG.md` for breaking changes (deferred-value semantics,
   tag-on-non-default-store fix, callable invocation count).
2. Reinstall guidelines: `php artisan boost:install`.
3. Audit any constraints written as `where('col', 'helperName')` where
   `helperName` happens to be a registered helper function — those are
   now treated as literal values, not invoked.
4. If you have batched `one()` / `many()` lookups that share a relation
   with different shapes and you'd like the merge behavior, set
   `query.merge_shared_eager_loads => true` in `config/dataproxy.php`.

## [0.4.0] - 2026-04-25

### Added
- Laravel 13 support.

## [0.3.0] - 2026-03-22

### Added
- Documentation of the multiple-scopes pattern across the core guideline
  and the web-views and infinite-scroll skills.

## [0.2.0] - 2026-03-03

### Added
- `hydrate()` documentation for batch-loading data after pagination
  (paginated-tables and infinite-scroll skills).

## [0.1.0] - 2026-02-27

Initial release.

### Added
- Core guideline (`core.blade.php`): package overview, when-to-use,
  core concepts, file-structure conventions, data-class pattern, and
  best practices / anti-patterns.
- Skill: `data-proxy-data-classes` — creating reusable data classes.
- Skill: `data-proxy-web-views` — building data for Blade and Inertia.
- Skill: `data-proxy-paginated-tables` — paginated, sortable,
  filterable tables for SaaS admin panels.
- Skill: `data-proxy-infinite-scroll` — feeds with cursor-based
  pagination and "load more".
- Service provider for Laravel auto-discovery of the guideline and
  skill resources by Laravel Boost.

[Unreleased]: https://github.com/adrorocker/laravel-data-proxy-boost/compare/0.5.0...HEAD
[0.5.0]: https://github.com/adrorocker/laravel-data-proxy-boost/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/adrorocker/laravel-data-proxy-boost/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/adrorocker/laravel-data-proxy-boost/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/adrorocker/laravel-data-proxy-boost/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/adrorocker/laravel-data-proxy-boost/releases/tag/0.1.0
