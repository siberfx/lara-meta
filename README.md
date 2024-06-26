# Laravel Meta Tags

[![Latest Stable Version](https://poser.pugx.org/siberfx/lara-meta/v/stable)](https://packagist.org/packages/siberfx/lara-meta)
[![Total Downloads](https://poser.pugx.org/siberfx/lara-meta/downloads)](https://packagist.org/packages/siberfx/lara-meta)
[![Latest Unstable Version](https://poser.pugx.org/siberfx/lara-meta/v/unstable)](https://packagist.org/packages/siberfx/lara-meta)
[![License](https://poser.pugx.org/siberfx/lara-meta/license)](https://packagist.org/packages/siberfx/lara-meta)

With this package you can manage header Meta Tags from Laravel controllers.

----------

## Installation

- [Laravel MetaTags on Packagist](https://packagist.org/packages/siberfx/lara-meta)
- [Laravel MetaTags on GitHub](https://github.com/siberfx/lara-meta)

From the command line run

```
$ composer require siberfx/lara-meta
```

Meta Tags also ships with a facade which provides the static syntax for creating collections. You can register the
facade in the `aliases` key of your `config/app.php` file if its Laravel 10.x.

```php
'aliases' => [
    // ...
    'MetaTag'   => Siberfx\LaraMeta\Facades\MetaTag::class,

]
```

### Publish the configurations

Run this on the command line from the root of your project:

```
$ php artisan vendor:publish --provider="Siberfx\LaraMeta\MetaTagsServiceProvider"
```

A configuration file will be publish to `config/meta-tags.php`.

## Twitter Cards and OpenGraph

Various settings for these options can be found in the `config/meta-tags.php` file.

**Twitter Cards**

```php
{!! MetaTag::twitterCard() !!}
```

**OpenGraph**

```php
{!! MetaTag::openGraph() !!}
```

## Examples

#### app/Http/Controllers/Controller.php

```php
<?php 

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesCommands;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;

use MetaTag;

abstract class Controller extends BaseController 
{
    use DispatchesCommands, ValidatesRequests;

    public function __construct()
    {
        // Defaults
        MetaTag::set('description', 'description of the page or content you desire to be visible on google searches');
        MetaTag::set('image', asset('images/default-share-image.png'));
    }
}
```

#### app/Http/Controllers/HomeController.php

```php
<?php 

namespace App\Http\Controllers;

use MetaTag;

class HomeController extends Controller 
{
    public function index()
    {
        // Section description
        MetaTag::set('title', 'You are at home');
        MetaTag::set('description', 'This is my home. Enjoy!');
        MetaTag::set('keywords', 'This is my home. Enjoy!');
        MetaTag::set('image', asset('images/detail-logo.png'));
        MetaTag::set('canonical', 'http://example.com');

        MetaTag::set('robots', 'index,follow');

   return view('index');
    }

    public function detail()
    {
        // Section description
        MetaTag::set('title', 'This is a detail page');
        MetaTag::set('description', 'All about this detail page');
        MetaTag::set('image', asset('images/detail-logo.png'));

        return view('detail');
    }

    public function private()
    {
        // Section description
        MetaTag::set('title', 'Private Area');
        MetaTag::set('description', 'You shall not pass!');
        MetaTag::set('image', asset('images/locked-logo.png'));

        return view('private');
    }
}
```

#### resources/views/layouts/app.blade.php

```php
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ MetaTag::get('title') . ' :: '. config('app.name')  }}</title>

        {!! MetaTag::tag('description') !!}
        {!! MetaTag::tag('keywords') !!}
        {!! MetaTag::tag('image') !!}
        {!! MetaTag::tag('image') !!}
        {!! MetaTag::tag('canonical') !!}
        {!! MetaTag::tag('robots') !!}
        
        {!! MetaTag::openGraph() !!}
        
        {!! MetaTag::twitterCard() !!}

        {{--Set default share picture after custom section pictures--}}
        {!! MetaTag::tag('image', asset('images/default-logo.png')) !!}
    </head>

    <body>
        @yield('content')
    </body>
</html>
```
