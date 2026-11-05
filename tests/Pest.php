<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Siberfx\LaraMeta\MetaTag;
use Siberfx\LaraMeta\Tests\TestCase;

/*
| Feature tests boot a Laravel application through Orchestra Testbench;
| unit tests run against a bare MetaTag instance.
*/
pest()->extend(TestCase::class)->in('Feature');

/**
 * @param  array<string, mixed>  $config
 */
function metaTag(array $config = [], string $url = 'https://www.example.com/blog/post?page=2'): MetaTag
{
    return new MetaTag(Request::create($url), $config, 'en');
}
