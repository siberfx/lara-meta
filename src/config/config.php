<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default title
    |--------------------------------------------------------------------------
    |
    | Used until a page sets its own title via MetaTag::set('title', ...).
    |
    */

    'title' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Automatic defaults
    |--------------------------------------------------------------------------
    |
    | When "auto_middleware" is true the package appends a middleware to the
    | "web" group that applies "defaults" (any meta key, e.g. description,
    | image, robots) and, when "json_ld" is true, a schema.org WebSite
    | document. Controllers run afterwards and override these values.
    | Disable it if you use the published DefaultMetaTags middleware instead.
    |
    */

    'auto_middleware' => true,

    'json_ld' => true,

    'defaults' => [
        // 'description' => 'Your default description.',
        // 'image' => 'https://example.com/share-default.png',
        // 'robots' => 'index,follow',
    ],

    /*
    |--------------------------------------------------------------------------
    | Title suffix
    |--------------------------------------------------------------------------
    |
    | Appended to the page title when rendering <title> (e.g. "Hello | My Site").
    | The title length limit only applies to the page part. Leave empty to disable.
    |
    */

    'title_suffix' => '',

    'title_separator' => ' | ',

    /*
    |--------------------------------------------------------------------------
    | Length limits
    |--------------------------------------------------------------------------
    |
    | Any "{key}_limit" entry truncates that key on a word boundary when it is
    | set. Search engines typically display ~60-70 title characters and
    | ~155-200 description characters.
    |
    */

    'title_limit' => 70,

    'description_limit' => 200,

    /*
    |--------------------------------------------------------------------------
    | OpenGraph
    |--------------------------------------------------------------------------
    |
    | Values here take precedence over values set at runtime.
    |
    */

    'open_graph' => [
        'site_name' => env('APP_NAME', 'Laravel'),
        'type' => 'website',
    ],

    /*
    |--------------------------------------------------------------------------
    | Twitter (X) cards
    |--------------------------------------------------------------------------
    |
    | Values here take precedence over values set at runtime.
    |
    */

    'twitter' => [
        'card' => 'summary',
        // 'site' => '@your_handle',
        // 'creator' => '@your_handle',
    ],

    /*
    |--------------------------------------------------------------------------
    | Localized URL template
    |--------------------------------------------------------------------------
    |
    | Template for the hreflang alternate links rendered by canonical().
    | Placeholders: [scheme], [locale], [lang], [host], [uri]. [locale] is empty
    | for the application's default locale and "{locale}." otherwise; [lang] is
    | always the lowercase locale code (e.g. '[scheme]://[host]/[lang][uri]').
    | [host] has a leading "www." removed. For full control use a closure
    | fn (string $locale, Request $request): string, or MetaTag::localizeUsing().
    | (Closures cannot be cached with `config:cache`; prefer localizeUsing().)
    |
    */

    'locale_url' => '[scheme]://[locale][host][uri]',

    /*
    |--------------------------------------------------------------------------
    | x-default hreflang
    |--------------------------------------------------------------------------
    |
    | Render an hreflang="x-default" alternate pointing at the default locale.
    |
    */

    'x_default' => true,

    /*
    |--------------------------------------------------------------------------
    | Output indentation
    |--------------------------------------------------------------------------
    |
    | Whitespace written after every tag. Use "" for compact output.
    |
    */

    'indent' => '    ',

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | An array of locale codes, or a callable returning one.
    |
    */

    'locales' => ['en', 'tr'],

];
