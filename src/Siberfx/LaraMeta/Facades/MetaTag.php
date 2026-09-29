<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static void setLocales(iterable<mixed> $locales = [])
 * @method static string|null get(string $key, string|null $default = null)
 * @method static string set(string $key, \Stringable|string|int|float|null $value = null)
 * @method static bool has(string $key)
 * @method static void forget(string $key)
 * @method static string tag(string $key, \Stringable|string|null $value = '')
 * @method static string canonical()
 * @method static string openGraph()
 * @method static string fbAppId()
 * @method static string twitterCard()
 *
 * @see \Siberfx\LaraMeta\MetaTag
 */
class MetaTag extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \Siberfx\LaraMeta\MetaTag::class;
    }
}
