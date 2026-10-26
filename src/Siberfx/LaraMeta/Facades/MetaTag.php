<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static void setLocales(iterable<mixed> $locales = [])
 * @method static void localizeUsing((\Closure(string, \Illuminate\Http\Request): string)|null $resolver)
 * @method static string|null get(string $key, string|null $default = null)
 * @method static string set(string $key, \Stringable|string|int|float|null $value = null)
 * @method static bool has(string $key)
 * @method static void forget(string $key)
 * @method static void noindex(bool $follow = false)
 * @method static void index(bool $follow = true)
 * @method static void addJsonLd(array<array-key, mixed> $data)
 * @method static string fullTitle()
 * @method static string titleTag()
 * @method static string jsonLd()
 * @method static \Illuminate\Support\HtmlString render()
 * @method static string toHtml()
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
