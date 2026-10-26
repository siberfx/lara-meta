# Upgrade guide

## To 7.4 from 7.3

Requires PHP 8.4+ and Laravel 12 or 13.

### Behaviour changes that can affect your output

- **`canonical()` now renders `<link>` elements.** It previously emitted invalid `<meta rel="canonical">` /
  `<meta rel="alternate" hreflang>` tags that search engines ignore. Update any tests that assert the old markup.
- **`hreflang="x-default"`** is rendered when locales are configured. Set `'x_default' => false` to opt out.
- **`[host]` in `locale_url`** is now the full host without a leading `www.` (it used to keep the last two labels,
  which broke `example.co.uk`). Hard-coded workarounds keep working.
- **`set('url', …)`** is now honoured by `canonical()` and `openGraph()`.
- **`fbAppId()`** returns an empty string when no `fb:app_id` is set (it used to render an empty tag).
- **Default Twitter `site`/`creator`** are no longer set to `@siberfx`. Re-add your handles in the `twitter` config
  if you relied on the old defaults.
- **Sanitising** only strips tag-shaped markup, so text like `a < b` is kept.
- **Values are stored unescaped** and escaped at render time: `get()` returns `"` rather than `&quot;`, so drop any
  manual `htmlspecialchars_decode()` and don't use `{!! MetaTag::get(…) !!}` for text content.
- **Scoped binding:** the service is created per request. Set values in a middleware or controller, not in a
  service provider's `boot()`.
- **Removed:** the magic `set{Key}()` dispatch.

### New

A middleware is now **registered automatically** on the `web` group (config `auto_middleware`, `json_ld`, `defaults`)
and adds a `WebSite` JSON-LD document to pages that render `@metaTags` / `jsonLd()`. Set `'auto_middleware' => false`
to opt out.


`render()` / `@metaTags` / `<x-meta-tags />`, `titleTag()` with `title_suffix`, `noindex()` / `index()`,
`addJsonLd()`, `localizeUsing()`, the `[lang]` placeholder, `indent` config, and extra OpenGraph
(`image:width|height|alt`, `article:*`) and Twitter (`image`, `image:alt`) tags.

Republish the config if you want the new keys (`php artisan vendor:publish --tag=meta-tags-config --force`); the
package merges its defaults, so it is optional.
