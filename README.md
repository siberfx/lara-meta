# Lara Meta

[![PHP 8.4 | 8.5](https://img.shields.io/badge/PHP-8.4%20%7C%208.5-777BB4?logo=php&logoColor=white)](https://www.php.net/supported-versions.php)
[![Laravel 12 | 13](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/docs/releases)
[![Tests](https://github.com/siberfx/lara-meta/actions/workflows/tests.yml/badge.svg)](https://github.com/siberfx/lara-meta/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/siberfx/lara-meta/v/stable)](https://packagist.org/packages/siberfx/lara-meta)
[![Total Downloads](https://poser.pugx.org/siberfx/lara-meta/downloads)](https://packagist.org/packages/siberfx/lara-meta)
[![License](https://poser.pugx.org/siberfx/lara-meta/license)](https://packagist.org/packages/siberfx/lara-meta)

Set page titles, meta descriptions, OpenGraph, Twitter (X) cards and `hreflang` alternates from your
controllers, then render them in your layout with one line each.

- Values are sanitised on the way in (HTML stripped, whitespace collapsed) and escaped on the way out.
- Titles and descriptions are truncated on a word boundary, multibyte-safe (`Çağrı…`, not broken bytes).
- Request-scoped: safe under Laravel Octane and long-running queue workers.

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Usage](#usage)
  - [Setting and reading values](#setting-and-reading-values)
  - [Facade or dependency injection](#facade-or-dependency-injection)
  - [Site-wide defaults](#site-wide-defaults)
  - [Rendering tags](#rendering-tags)
  - [Title and description limits](#title-and-description-limits)
  - [OpenGraph](#opengraph)
  - [Twitter (X) cards](#twitter-x-cards)
  - [Facebook app ID](#facebook-app-id)
  - [Canonical and hreflang alternates](#canonical-and-hreflang-alternates)
  - [Escaping and sanitising](#escaping-and-sanitising)
  - [Octane and queue workers](#octane-and-queue-workers)
  - [Testing your pages](#testing-your-pages)
- [API reference](#api-reference)
- [Configuration](#configuration)
- [Testing the package](#testing-the-package)

## Requirements

| Package | PHP        | Laravel    |
| ------- | ---------- | ---------- |
| 7.4+    | 8.4 – 8.5  | 12.x, 13.x |

Every PHP / Laravel combination below is tested in CI, against both the lowest and the latest allowed dependencies.

| Laravel | PHP 8.4 | PHP 8.5 |
| ------- | :-----: | :-----: |
| 12.x    | ✅      | ✅      |
| 13.x    | ✅      | ✅      |

## Installation

```bash
composer require siberfx/lara-meta
```

The service provider and the `MetaTag` facade are auto-discovered, and the default configuration is merged
automatically, so nothing else is required.

To customise the defaults, publish the package files:

```bash
php artisan vendor:publish --tag=meta-tags
```

| Tag                    | Publishes                                                                                   |
| ---------------------- | ------------------------------------------------------------------------------------------- |
| `meta-tags`            | Both files below.                                                                           |
| `meta-tags-config`     | The config, to `config/meta-tags.php`.                                                      |
| `meta-tags-middleware` | A [defaults middleware](#site-wide-defaults), to `app/Http/Middleware/DefaultMetaTags.php`. |

Existing files are never overwritten unless you add `--force`.

The middleware is not active until you register it (see [Site-wide defaults](#site-wide-defaults)).

## Quick start

**1. Set values in a controller:**

```php
use Siberfx\LaraMeta\Facades\MetaTag;

final class PostController
{
    public function show(Post $post): View
    {
        MetaTag::set('title', $post->title);
        MetaTag::set('description', $post->excerpt);
        MetaTag::set('image', $post->cover_url);

        return view('posts.show', compact('post'));
    }
}
```

**2. Render them in your layout's `<head>`:**

```blade
<title>{{ MetaTag::get('title') }}</title>

{!! MetaTag::tag('description') !!}
{!! MetaTag::openGraph() !!}
{!! MetaTag::twitterCard() !!}
```

That's it. The rest of this page covers each feature in detail.

## Usage

### Setting and reading values

`set()` stores any key/value pair and returns the value as it was stored (sanitised and, if a limit is
configured for that key, truncated):

```php
MetaTag::set('title', 'Hello World');
MetaTag::set('description', 'An introduction to our blog.');
MetaTag::set('keywords', 'laravel, php, seo');
MetaTag::set('robots', 'index,follow');
MetaTag::set('image', asset('images/hello.png'));

$stored = MetaTag::set('description', '<p>Some <b>HTML</b></p>'); // "Some HTML"
```

`set()` accepts strings, numbers, `null` and any `Stringable` (such as `Str::of(...)` or an `HtmlString`):

```php
MetaTag::set('description', Str::of($post->body)->stripTags()->words(30));
MetaTag::set('rating', 4.5);        // "4.5"
MetaTag::set('description', null);  // ""
```

Read, check and remove values:

```php
MetaTag::get('title');                       // "Hello World"
MetaTag::get('author');                      // null
MetaTag::get('author', 'Editorial team');    // "Editorial team"

MetaTag::has('robots');                      // true (set and not empty)
MetaTag::forget('robots');
MetaTag::has('robots');                      // false
```

Two values are filled in automatically when the instance is created:

| Key     | Initial value                                            |
| ------- | -------------------------------------------------------- |
| `title` | The `title` config value (defaults to `APP_NAME`)        |
| `url`   | The current URL, without the query string                 |

### Facade or dependency injection

The facade and the container return the **same instance** for the current request, so you can mix them freely.

```php
use Siberfx\LaraMeta\Facades\MetaTag;

MetaTag::set('title', 'Pricing');
```

```php
use Siberfx\LaraMeta\MetaTag;

final class PricingController
{
    public function __invoke(MetaTag $meta): View
    {
        $meta->set('title', 'Pricing');
        $meta->set('description', 'Simple plans for teams of every size.');

        return view('pricing');
    }
}
```

The instance also exposes read-only properties:

```php
$meta = app(MetaTag::class);

$meta->title;          // current title (same as get('title', ''))
$meta->metas;          // ['title' => 'Pricing', 'url' => 'https://…', 'description' => '…']
$meta->locales;        // ['en', 'tr']
$meta->defaultLocale;  // the app locale, e.g. 'en'
```

### Automatic defaults

Out of the box the package appends a middleware to the `web` group that applies the `defaults` config
(any meta key) and a schema.org `WebSite` JSON-LD document before your controllers run, so `@metaTags` outputs
JSON-LD with zero setup:

```php
// config/meta-tags.php
'auto_middleware' => true,   // register the middleware on the "web" group
'json_ld' => true,           // include the WebSite JSON-LD document
'defaults' => [
    'description' => 'Your default description.',
    'robots' => 'index,follow',
],
```

Set `json_ld` to `false` to drop the JSON-LD, or `auto_middleware` to `false` to turn everything off (for example
when you use the published `DefaultMetaTags` middleware below). Identical JSON-LD documents are only rendered once,
so using both is harmless.

### Site-wide defaults

Set defaults once and let individual pages override them. Values set later win, so anything a controller sets
replaces the default.

**In a middleware** (recommended, runs for every web request). The package ships a ready-made one; publish it
to `app/Http/Middleware/DefaultMetaTags.php`:

```bash
php artisan vendor:publish --tag=meta-tags-middleware
```

It looks like this. Edit the values to suit your site:

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Siberfx\LaraMeta\Facades\MetaTag;
use Symfony\Component\HttpFoundation\Response;

final class DefaultMetaTags
{
    public function handle(Request $request, Closure $next): Response
    {
        MetaTag::set('description', config('app.name').' — replace this with your default description.');
        MetaTag::set('image', asset('images/share-default.png'));
        MetaTag::set('robots', app()->isProduction() ? 'index,follow' : 'noindex,nofollow');

        return $next($request);
    }
}
```

The `robots` line keeps staging and local environments out of search results.

Register it on the `web` group in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        \App\Http\Middleware\DefaultMetaTags::class,
    ]);
})
```

**Or in a base controller constructor**, if you prefer:

```php
abstract class Controller
{
    public function __construct()
    {
        MetaTag::set('image', asset('images/share-default.png'));
    }
}
```

### Rendering tags

**The short way.** Render the whole head block (title with optional suffix, description, robots, canonical +
hreflang, OpenGraph, `fb:app_id`, Twitter card and JSON-LD) with one call:

```blade
<head>
    <meta charset="utf-8">
    @metaTags          {{-- or <x-meta-tags /> or {{ MetaTag::render() }} --}}
</head>
```

**The explicit way.** Render individual tags with `{!! !!}` because the methods return ready-made, already-escaped HTML. A complete layout:

```blade
{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {!! MetaTag::titleTag() !!}

    {!! MetaTag::tag('description') !!}
    {!! MetaTag::tag('keywords') !!}
    {!! MetaTag::tag('robots') !!}

    {!! MetaTag::canonical() !!}
    {!! MetaTag::openGraph() !!}
    {!! MetaTag::twitterCard() !!}
    {!! MetaTag::fbAppId() !!}
</head>
<body>
    @yield('content')
</body>
</html>
```

`tag()` renders the stored value for a key:

```php
MetaTag::set('description', 'An introduction to our blog.');

MetaTag::tag('description');
// <meta name="description" content="An introduction to our blog.">
```

Pass a second argument to use a different value **for that one tag only** (the stored value is not changed).
This is handy for fallbacks:

```blade
{!! MetaTag::tag('image', MetaTag::get('image') ?: asset('images/share-default.png')) !!}
```

Only render a tag when a value exists:

```blade
@if (MetaTag::has('keywords'))
    {!! MetaTag::tag('keywords') !!}
@endif
```

Use `get()` with `{{ }}` for text content such as the `<title>`. Blade escapes it, and because values are stored
unescaped you never get double encoding (`&amp;quot;`).

**Tip:** keep your `<head>` tidy by moving the tags into a partial:

```blade
{{-- resources/views/partials/meta.blade.php --}}
<title>{{ MetaTag::get('title') }}</title>
{!! MetaTag::tag('description') !!}
{!! MetaTag::canonical() !!}
{!! MetaTag::openGraph() !!}
{!! MetaTag::twitterCard() !!}
```

```blade
<head>
    <meta charset="utf-8">
    @include('partials.meta')
</head>
```

### Title suffix

Set `title_suffix` (and optionally `title_separator`, default `' | '`) in the config and `titleTag()` renders
`<title>Hello | My Site</title>`. `fullTitle()` returns the same text as a string. `title_limit` only applies to the
page part. The suffix is skipped when the title equals it (e.g. on the home page).

### Robots

```php
MetaTag::noindex();                 // noindex,nofollow
MetaTag::noindex(follow: true);     // noindex,follow
MetaTag::index();                   // index,follow
```

### JSON-LD structured data

```php
MetaTag::addJsonLd([
    '@type' => 'Article',
    'headline' => $post->title,
    'datePublished' => $post->published_at->toIso8601String(),
]);
```

`@context` defaults to `https://schema.org`. Each document renders as a `<script type="application/ld+json">`
(`</script>` and `&` are hex-escaped, so user content cannot break out). Render with `{!! MetaTag::jsonLd() !!}`
or let `@metaTags` include it.

### Title and description limits

Any key can have a `{key}_limit` in the config. When a value is longer than its limit, it is cut on the last
word boundary and `...` is appended, so the result (including the dots) never exceeds the limit.

The defaults are `title_limit` 70 and `description_limit` 200:

```php
MetaTag::set('title', 'The Complete, Illustrated and Unabridged Guide to Frying Fish and Chips at Home');
// "The Complete, Illustrated and Unabridged Guide to Frying Fish and..."
```

Add limits for your own keys in `config/meta-tags.php`:

```php
'keywords_limit' => 150,
```

Limits count characters, not bytes, so multibyte text such as Turkish, Arabic or emoji is never cut
mid-character.

### OpenGraph

`openGraph()` always renders `og:url` (the current URL without the query string) and then, in this order, every
one of the following properties that has a value:

`title`, `description`, `type`, `image`, `url`, `audio`, `determiner`, `locale`, `site_name`, `video`

Each property is taken from the `open_graph` config array first and falls back to the value set with `set()`
under the same key:

```php
// config/meta-tags.php
'open_graph' => [
    'site_name' => 'Acme',
    'type' => 'website',
],
```

```php
MetaTag::set('title', 'Hello World');
MetaTag::set('description', 'An introduction to our blog.');
MetaTag::set('image', 'https://example.com/images/hello.png');
```

```blade
{!! MetaTag::openGraph() !!}
```

```html
<meta property="og:url" content="https://www.example.com/blog/hello-world">
<meta property="og:title" content="Hello World">
<meta property="og:description" content="An introduction to our blog.">
<meta property="og:type" content="website">
<meta property="og:image" content="https://example.com/images/hello.png">
<meta property="og:site_name" content="Acme">
```

Because config values win, remove a key from `open_graph` if you want to set it per page. For example, to use
`article` on blog posts, drop `type` from the config and set it yourself:

```php
MetaTag::set('type', 'article');   // on blog posts
MetaTag::set('type', 'website');   // everywhere else, e.g. in the defaults middleware
```

Other OpenGraph properties work the same way:

```php
MetaTag::set('locale', 'en_US');
MetaTag::set('video', 'https://example.com/videos/intro.mp4');
MetaTag::set('audio', 'https://example.com/audio/intro.mp3');
```

### Twitter (X) cards

`twitterCard()` renders these properties, in order, when they have a value:

`card`, `site`, `title`, `description`, `creator`, `image:src`, `domain`

As with OpenGraph, the `twitter` config array wins over values set at runtime. Two fallbacks apply:

| Property    | Fallback when not configured or set          |
| ----------- | -------------------------------------------- |
| `image:src` | The `image` value                            |
| `domain`    | The current request host                     |

```php
// config/meta-tags.php
'twitter' => [
    'card' => 'summary_large_image',
    'site' => '@acme',
    'creator' => '@acme',
],
```

```html
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@acme">
<meta name="twitter:title" content="Hello World">
<meta name="twitter:description" content="An introduction to our blog.">
<meta name="twitter:creator" content="@acme">
<meta name="twitter:image:src" content="https://example.com/images/hello.png">
<meta name="twitter:domain" content="www.example.com">
```

To credit each post's author, remove `creator` from the config and set it per page:

```php
MetaTag::set('creator', '@'.$post->author->twitter_handle);
```

### Facebook app ID

```php
MetaTag::set('fb:app_id', config('services.facebook.app_id'));
```

```blade
{!! MetaTag::fbAppId() !!}
{{-- <meta property="fb:app_id" content="1234567890"> --}}
```

### Canonical and hreflang alternates

`canonical()` renders the canonical `<link>` (it honours a `url` set with `MetaTag::set('url', …)`), one
`hreflang` alternate per configured locale and an `x-default` alternate for the default locale (disable with
`'x_default' => false`):

```php
// config/meta-tags.php
'locales' => ['en', 'tr'],
'locale_url' => '[scheme]://[locale][host][uri]',
```

For a request to `https://www.example.com/blog/hello-world?page=2` with the app locale set to `en`:

```html
<link rel="canonical" href="https://www.example.com/blog/hello-world">
<link rel="alternate" hreflang="en" href="https://example.com/blog/hello-world?page=2">
<link rel="alternate" hreflang="tr" href="https://tr.example.com/blog/hello-world?page=2">
<link rel="alternate" hreflang="x-default" href="https://example.com/blog/hello-world?page=2">
```

How the alternate URLs are built from `locale_url`:

| Placeholder | Value                                                                                        |
| ----------- | -------------------------------------------------------------------------------------------- |
| `[scheme]`  | `http` or `https`                                                                            |
| `[locale]`  | Empty for the default locale (`app.locale`), otherwise the lower-cased locale plus a dot (`tr.`) |
| `[lang]`    | The lower-cased locale code (`tr`), for path-based locales                                   |
| `[host]`    | The host without a leading `www.` (`www.example.com` becomes `example.com`)                  |
| `[uri]`     | The path and query string                                                                    |

**Path-based locales** (`/tr/blog/...`): use `[lang]`:

```php
'locale_url' => '[scheme]://[host]/[lang][uri]',
```

**Full control**: resolve alternates with a closure (in a middleware or service provider `boot()` of your app,
not in config, so `config:cache` keeps working):

```php
MetaTag::localizeUsing(fn (string $locale, Request $request): string => url("/{$locale}/".$request->path()));
```

**Locales from your database or another package.** Set them at runtime in a middleware (not in a service
provider's `boot()`: the instance is recreated for every request, so values set at boot time are lost):

```php
MetaTag::setLocales(Language::query()->where('active', true)->pluck('code'));
```

`setLocales()` accepts any iterable (arrays, collections, generators). The `locales` config key also accepts a
callable, but closures prevent `php artisan config:cache` from working, so prefer an array or `setLocales()`.

**Disable alternates** by setting an empty list:

```php
'locales' => [],
```

### Escaping and sanitising

You never need to escape values yourself.

| Step              | What happens                                                                              |
| ----------------- | ----------------------------------------------------------------------------------------- |
| On `set()`        | HTML tags are stripped, all whitespace (including newlines) collapses to single spaces, then the value is trimmed and limited. |
| On render         | Attribute values are HTML-escaped (`&`, `"`, `'`, `<`, `>`). Existing entities such as `&amp;` are left as they are. |

```php
MetaTag::set('description', "An <strong>introduction</strong> to\n   Fish & \"Chips\".");

MetaTag::get('description');
// An introduction to Fish & "Chips".

MetaTag::tag('description');
// <meta name="description" content="An introduction to Fish &amp; &quot;Chips&quot;.">
```

That makes it safe to pass user-generated content such as post bodies or comments straight to `set()`.

### Octane and queue workers

The `MetaTag` instance is bound as a **scoped** service. Laravel creates a fresh instance for every HTTP request,
Octane request and queued job, so values set for one visitor never leak into the next response. No extra setup
is needed.

### Testing your pages

Because the tags are plain HTML, assert on the response like any other markup:

```php
public function test_post_page_has_meta_tags(): void
{
    $post = Post::factory()->create(['title' => 'Hello World']);

    $this->get(route('posts.show', $post))
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Hello World">', escape: false)
        ->assertSee('<meta name="twitter:title" content="Hello World">', escape: false);
}
```

Or assert on the stored values directly after a request:

```php
use Siberfx\LaraMeta\Facades\MetaTag;

$this->get(route('posts.show', $post));

$this->assertSame('Hello World', MetaTag::get('title'));
```

## API reference

| Method                                  | Returns   | Description                                                                 |
| --------------------------------------- | --------- | --------------------------------------------------------------------------- |
| `set(string $key, $value = null)`       | `string`  | Store a value (sanitised and length-limited) and return what was stored.    |
| `get(string $key, ?string $default)`    | `?string` | Read a stored value, or `$default` when it is missing.                      |
| `has(string $key)`                      | `bool`    | Whether a non-empty value is stored.                                        |
| `forget(string $key)`                   | `void`    | Remove a value.                                                             |
| `tag(string $key, $value = '')`         | `string`  | `<meta name="…" content="…">`, using `$value` when given.                   |
| `canonical()`                           | `string`  | Canonical link, `hreflang` alternates per locale and `x-default`.           |
| `render()` / `toHtml()`                 | `HtmlString` / `string` | The whole head block. Also available as `@metaTags` and `<x-meta-tags />`. |
| `titleTag()` / `fullTitle()`            | `string`  | `<title>` element / title with the configured suffix.                       |
| `noindex(bool $follow)` / `index(bool $follow)` | `void` | Set the `robots` value.                                              |
| `addJsonLd(array $data)` / `jsonLd()`   | `void` / `string` | Queue / render JSON-LD documents.                                   |
| `localizeUsing(?Closure $resolver)`     | `void`    | Resolve hreflang URLs with a callback instead of `locale_url`.              |
| `openGraph()`                           | `string`  | `og:*` tags. Values in the `open_graph` config win over runtime values.     |
| `twitterCard()`                         | `string`  | `twitter:*` tags. Values in the `twitter` config win over runtime values.   |
| `fbAppId()`                             | `string`  | `fb:app_id` tag from the `fb:app_id` value.                                 |
| `setLocales(iterable $locales)`         | `void`    | Replace the locales used by `canonical()`.                                  |

| Read-only property | Type            | Description                              |
| ------------------ | --------------- | ---------------------------------------- |
| `title`            | `string`        | The current title.                       |
| `metas`            | `array`         | All stored values, keyed by name.        |
| `locales`          | `list<string>`  | Locales used for `hreflang` alternates.  |
| `defaultLocale`    | `string`        | The app locale when the instance was created. |

## Configuration

Published to `config/meta-tags.php`:

| Key                 | Default                                     | Purpose                                                                   |
| ------------------- | ------------------------------------------- | ------------------------------------------------------------------------- |
| `title`             | `APP_NAME`                                  | Default title until a page sets one.                                      |
| `{key}_limit`       | `title_limit: 70`, `description_limit: 200` | Maximum length for any key; truncated on a word boundary with `...`.      |
| `open_graph`        | `site_name`, `type`                         | Fixed OpenGraph values. They win over values set at runtime.              |
| `title_suffix`      | `''`                                        | Appended to the title by `titleTag()`; `title_separator` defaults to `' \| '`. |
| `x_default`         | `true`                                      | Render the `hreflang="x-default"` alternate.                              |
| `indent`            | `'    '`                                    | Whitespace written after every tag (`''` for compact output).             |
| `twitter`           | `card`                                      | Fixed Twitter card values. They win over values set at runtime.           |
| `locale_url`        | `[scheme]://[locale][host][uri]`            | Template (or closure) for `hreflang` URLs; also `[lang]`.                 |
| `locales`           | `['en', 'tr']`                              | Locales for `hreflang` alternates, or a callable returning them.          |

## Testing the package

```bash
composer test      # PHPUnit
composer analyse   # PHPStan (level max, Larastan)
composer lint      # Pint
composer ci        # all of the above
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
