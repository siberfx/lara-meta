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
        'creator' => '@siberfx',
        'site' => '@siberfx',
    ],

    /*
    |--------------------------------------------------------------------------
    | Localized URL template
    |--------------------------------------------------------------------------
    |
    | Template for the hreflang alternate links rendered by canonical().
    | Placeholders: [scheme], [locale], [host], [uri]. [locale] is empty for
    | the application's default locale and "{locale}." otherwise.
    |
    */

    'locale_url' => '[scheme]://[locale][host][uri]',

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
