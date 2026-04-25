# Laravel Data Proxy Boost

Laravel Boost guidelines and skills for [adrosoftware/laravel-data-proxy](https://github.com/adrorocker/laravel-data-proxy).

This package provides AI coding assistants with guidelines and skills to help generate high-quality code using the Laravel Data Proxy package.

## Installation

```bash
composer require adrosoftware/laravel-data-proxy-boost --dev
```

The package will be auto-discovered by Laravel.

## Requirements

- PHP 8.2+ (PHP 8.3+ for Laravel 13)
- Laravel 11, 12, or 13
- Laravel Boost
- `adrosoftware/laravel-data-proxy` ^0.5 (in your application)

## What's Included

### Guidelines

Core conventions for using Laravel Data Proxy:
- Package overview and when to use it
- Core concepts (Requirements, Shape, Result, DataSet)
- File structure conventions
- Best practices and anti-patterns

### Skills

On-demand, task-specific patterns:

| Skill | Description |
|-------|-------------|
| `data-proxy-data-classes` | Creating reusable data classes |
| `data-proxy-web-views` | Building data for Blade/Inertia views |
| `data-proxy-paginated-tables` | Paginated tables for SaaS apps |
| `data-proxy-infinite-scroll` | Infinite scroll feeds |

## Usage

After installing, run Laravel Boost to install the guidelines and skills:

```bash
php artisan boost:install
```

The guidelines will be available in `.ai/guidelines/` and skills in `.ai/skills/`.

## License

MIT License. See [LICENSE](LICENSE) for details.
