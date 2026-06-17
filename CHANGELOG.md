# Changelog

All notable changes to `siberfx/lara-meta` are documented here. This project adheres to
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added
- Config is now merged automatically via `mergeConfigFrom`, so the package works out of the box
  without running `vendor:publish`.
- Tagged config publishing: `php artisan vendor:publish --tag=meta-tags-config`.
- `metatag` binding is now aliased to the `Siberfx\LaraMeta\MetaTag::class` for type-hinted resolution.

### Changed
- Targets **PHP ^8.4** and **Laravel 12 / 13** (`illuminate/support` and `illuminate/http` `^12|^13`).
- Service provider uses typed `boot(): void` / `register(): void` signatures and removed the legacy,
  non-functional `protected $defer` property.
- Config defaults use `env('APP_NAME', 'Laravel')` instead of `config('app.name')` to avoid
  config-load-order / config-cache fragility.

### Fixed
- No more "Undefined array key" warnings/errors when the config has not been published — all config
  access goes through `Arr::get` with safe defaults.
- PHP 8.4 deprecation: `fix()` no longer passes `null` to `preg_replace`/`str_replace`.
- Replaced the removed-style global `array_get()` helper with `Illuminate\Support\Arr::get`.

### Removed
- `laravel/helpers` dependency (no longer needed after dropping `array_get()`).
