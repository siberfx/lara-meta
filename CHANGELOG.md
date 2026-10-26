# Changelog

All notable changes to `siberfx/lara-meta` are documented here. This project adheres to
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [7.4.0] — 2026-10-26

Officially supports **PHP 8.4 and 8.5** on **Laravel 12 and 13**, and modernises the codebase.

### Added
- **Automatic middleware** (`auto_middleware`, default on) appended to the `web` group: applies the `defaults` config and a `WebSite` JSON-LD document (`json_ld`). Identical JSON-LD documents are rendered once.
- `render()` / `toHtml()` (the class is now `Htmlable`), the `@metaTags` Blade directive and `<x-meta-tags />` component.
- `titleTag()` / `fullTitle()` with `title_suffix` and `title_separator` config.
- `noindex()` / `index()` robots helpers.
- `addJsonLd()` / `jsonLd()` for JSON-LD structured data (script-safe encoding).
- `hreflang="x-default"` alternate (`x_default` config), `[lang]` placeholder, closure `locale_url` and `localizeUsing()`.
- `indent` config for compact output.
- OpenGraph `image:width|height|alt` and `article:*`; Twitter `image` and `image:alt`.
- `UPGRADE.md`, Dependabot, a tag-triggered release workflow, a coverage job and PHPStan/Pint on PHP 8.4 and 8.5.
- Publishable `DefaultMetaTags` middleware for site-wide defaults (description, image, robots and a `WebSite` JSON-LD document), published to
  `app/Http/Middleware/DefaultMetaTags.php` with `--tag=meta-tags-middleware`.
- `--tag=meta-tags` publishes the config and the middleware together.
- `has(string $key)` and `forget(string $key)`.
- Read-only properties: `$metas`, `$locales` (asymmetric visibility), `$title` (property hook) and `$defaultLocale`.
- `OpenGraphTag` and `TwitterCardTag` enums describing the rendered properties.
- `@method` annotations on the facade for IDE autocompletion.
- Test suite (PHPUnit + Orchestra Testbench), PHPStan level max (Larastan), Pint, and a GitHub Actions
  matrix for PHP 8.4 / 8.5 × Laravel 12 / 13 (lowest and stable dependencies).

### Changed
- **`canonical()` renders `<link>` elements** instead of invalid `<meta rel=…>` tags (see `UPGRADE.md`).
- `canonical()` and `openGraph()` honour `set('url', …)`; `fbAppId()` renders nothing when unset.
- `[host]` in `locale_url` is the host minus `www.` (was the last two labels, which broke `example.co.uk`).
- Default Twitter `site`/`creator` are no longer `@siberfx`.
- Sanitising only strips tag-shaped markup, so `a < b` survives.
- `composer.json` requires `php: ^8.4` and requires `ext-mbstring`.
- The service is bound **scoped** instead of as a singleton, so meta data and the captured `Request` never
  leak between requests under Octane or queue workers. The `metatag` container key is kept as an alias.
- Values are stored raw (HTML stripped, whitespace collapsed) and HTML-escaped at render time.
  `get()` now returns `"` instead of `&quot;`, so `{{ MetaTag::get('title') }}` no longer double-escapes.
  `&`, `<` and `'` in rendered attributes are now escaped as well. Existing entities are not re-encoded.
- Length limits are multibyte-safe (`mb_*`), so Turkish and other non-ASCII text is never cut mid-character.
- All classes use `declare(strict_types=1)`, typed signatures, constructor promotion and `#[\Override]`.
- The config file is only registered for publishing when running in the console.

### Fixed
- `hreflang` URLs on single-label hosts (e.g. `localhost`) no longer get a leading dot (`.localhost`).
- `set('locales', …)` no longer triggers a `TypeError` through the old magic `set{Key}()` dispatch.

### Removed
- The internal magic `set{Key}()` dispatch and the unused private `setTitle()` suffix logic. Titles are limited by
  `title_limit` exactly as before.
- `provides()` on the service provider (it had no effect because the provider is not deferred).

## [7.2.0]

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
