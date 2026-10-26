<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Override;
use Siberfx\LaraMeta\Enums\OpenGraphTag;
use Siberfx\LaraMeta\Enums\TwitterCardTag;
use Stringable;

/**
 * Collects page meta data during a request and renders it as HTML meta tags.
 *
 * Values are sanitised when stored (markup stripped, whitespace collapsed,
 * length-limited per config) and HTML-escaped when rendered.
 */
class MetaTag implements Htmlable
{
    public const string DEFAULT_LOCALE_URL = '[scheme]://[locale][host][uri]';

    private const string DEFAULT_INDENT = '    ';

    /**
     * Meta values set for the current request, keyed by name.
     *
     * @var array<string, string>
     */
    public private(set) array $metas = [];

    /**
     * Locale codes used to build `hreflang` alternate links.
     *
     * @var list<string>
     */
    public private(set) array $locales = [];

    /**
     * JSON-LD documents rendered as `application/ld+json` scripts.
     *
     * @var list<array<array-key, mixed>>
     */
    public private(set) array $jsonLd = [];

    /**
     * The current page title (shortcut for `get('title', '')`).
     */
    public string $title {
        get => $this->get('title', '');
    }

    /**
     * Resolves the alternate URL of a locale, replacing the `locale_url` template.
     *
     * @var (Closure(string, Request): string)|null
     */
    private ?Closure $localeResolver = null;

    /**
     * @param  array<array-key, mixed>  $config
     */
    public function __construct(
        private readonly Request $request,
        private readonly array $config = [],
        public readonly string $defaultLocale = 'en',
    ) {
        $this->set('title', $this->configString('title'));
        $this->set('url', $this->request->url());

        $locales = $this->config['locales'] ?? [];
        $locales = is_callable($locales) ? $locales() : $locales;

        $this->setLocales(is_iterable($locales) ? $locales : []);
    }

    /**
     * @param  iterable<mixed>  $locales
     */
    public function setLocales(iterable $locales = []): void
    {
        $this->locales = array_values(array_map(
            static fn (string|int|float|bool $locale): string => (string) $locale,
            array_filter([...$locales], is_scalar(...)),
        ));
    }

    /**
     * Resolve alternate locale URLs with a callback instead of the `locale_url` template.
     *
     * @param  (Closure(string, Request): string)|null  $resolver
     */
    public function localizeUsing(?Closure $resolver): void
    {
        $this->localeResolver = $resolver;
    }

    /**
     * @template TDefault of string|null
     *
     * @param  TDefault  $default
     * @return string|TDefault
     */
    public function get(string $key, ?string $default = null): ?string
    {
        return $this->metas[$key] ?? $default;
    }

    /**
     * Store a meta value and return the sanitised, length-limited result.
     */
    public function set(string $key, string|Stringable|int|float|null $value = null): string
    {
        return $this->metas[$key] = $this->limit($this->sanitize($value), $this->limitFor($key));
    }

    public function has(string $key): bool
    {
        return ($this->metas[$key] ?? '') !== '';
    }

    public function forget(string $key): void
    {
        unset($this->metas[$key]);
    }

    /**
     * Ask search engines not to index the page.
     */
    public function noindex(bool $follow = false): void
    {
        $this->set('robots', $follow ? 'noindex,follow' : 'noindex,nofollow');
    }

    /**
     * Allow search engines to index the page.
     */
    public function index(bool $follow = true): void
    {
        $this->set('robots', $follow ? 'index,follow' : 'index,nofollow');
    }

    /**
     * Queue a JSON-LD document (identical documents are added once); `@context` defaults to schema.org.
     *
     * @param  array<array-key, mixed>  $data
     */
    public function addJsonLd(array $data): void
    {
        $document = ['@context' => 'https://schema.org', ...$data];

        if (! in_array($document, $this->jsonLd, true)) {
            $this->jsonLd[] = $document;
        }
    }

    /**
     * The page title with the configured suffix, e.g. "Hello | My Site".
     */
    public function fullTitle(): string
    {
        $suffix = $this->configString('title_suffix');

        if ($suffix === '' || $this->title === '' || $this->title === $suffix) {
            return $this->title !== '' ? $this->title : $suffix;
        }

        return $this->title.$this->configString('title_separator', ' | ').$suffix;
    }

    /**
     * Render the `<title>` element.
     */
    public function titleTag(): string
    {
        $title = $this->fullTitle();

        return $title === '' ? '' : '<title>'.$this->escape($title).'</title>'.$this->lineEnd();
    }

    /**
     * Render a `<meta name="…">` tag, using the stored value unless one is given.
     */
    public function tag(string $key, string|Stringable|null $value = ''): string
    {
        $content = (string) $value;

        return $this->createElement('meta', [
            'name' => $key,
            'content' => $content !== '' ? $content : $this->get($key, ''),
        ]);
    }

    /**
     * Render the canonical link, one `hreflang` alternate per locale and,
     * unless `x_default` is false, an `x-default` alternate for the default locale.
     */
    public function canonical(): string
    {
        $html = $this->createElement('link', [
            'rel' => 'canonical',
            'href' => $this->get('url', $this->request->url()),
        ]);

        foreach ($this->locales as $locale) {
            $html .= $this->createElement('link', [
                'rel' => 'alternate',
                'hreflang' => $locale,
                'href' => $this->localizedUrl($locale),
            ]);
        }

        if ($this->locales !== [] && ($this->config['x_default'] ?? true) !== false) {
            $html .= $this->createElement('link', [
                'rel' => 'alternate',
                'hreflang' => 'x-default',
                'href' => $this->localizedUrl($this->defaultLocale),
            ]);
        }

        return $html;
    }

    public function openGraph(): string
    {
        $html = [
            OpenGraphTag::Url->value => $this->createElement('meta', [
                'property' => OpenGraphTag::Url->property(),
                'content' => $this->get('url', $this->request->url()),
            ]),
        ];

        foreach (OpenGraphTag::cases() as $tag) {
            $value = $this->resolve('open_graph', $tag->value);

            if ($value !== '') {
                $html[$tag->value] = $this->createElement('meta', ['property' => $tag->property(), 'content' => $value]);
            }
        }

        return implode('', $html);
    }

    public function fbAppId(): string
    {
        if (! $this->has('fb:app_id')) {
            return '';
        }

        return $this->createElement('meta', [
            'property' => 'fb:app_id',
            'content' => $this->get('fb:app_id', ''),
        ]);
    }

    public function twitterCard(): string
    {
        $fallbacks = [
            TwitterCardTag::Image->value => $this->get('image', ''),
            TwitterCardTag::ImageSrc->value => $this->get('image', ''),
            TwitterCardTag::Domain->value => $this->request->getHttpHost(),
        ];

        $html = [];

        foreach (TwitterCardTag::cases() as $tag) {
            $value = $this->resolve('twitter', $tag->value);
            $value = $value !== '' ? $value : ($fallbacks[$tag->value] ?? '');

            if ($value !== '') {
                $html[] = $this->createElement('meta', ['name' => $tag->property(), 'content' => $value]);
            }
        }

        return implode('', $html);
    }

    /**
     * Render every queued JSON-LD document as a script tag.
     */
    public function jsonLd(): string
    {
        $html = '';

        foreach ($this->jsonLd as $document) {
            $json = json_encode(
                $document,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PARTIAL_OUTPUT_ON_ERROR,
            );

            $html .= '<script type="application/ld+json">'.$json.'</script>'.$this->lineEnd();
        }

        return $html;
    }

    /**
     * Render the complete head block: title, description, robots, canonical,
     * OpenGraph, Facebook app id, Twitter card and JSON-LD.
     */
    public function render(): HtmlString
    {
        $html = $this->titleTag();

        foreach (['description', 'robots'] as $key) {
            $html .= $this->has($key) ? $this->tag($key) : '';
        }

        return new HtmlString(
            $html.$this->canonical().$this->openGraph().$this->fbAppId().$this->twitterCard().$this->jsonLd(),
        );
    }

    #[Override]
    public function toHtml(): string
    {
        return $this->render()->toHtml();
    }

    /**
     * Resolve a tag value: the config group wins, otherwise the value set at runtime.
     */
    private function resolve(string $group, string $key): string
    {
        $values = $this->config[$group] ?? [];
        $configured = is_array($values) ? ($values[$key] ?? null) : null;

        return is_scalar($configured) && (string) $configured !== ''
            ? (string) $configured
            : $this->get($key, '');
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function createElement(string $element, array $attributes): string
    {
        $pairs = array_map(
            fn (string $name, string $value): string => sprintf('%s="%s"', $name, $this->escape($this->sanitize($value))),
            array_keys($attributes),
            $attributes,
        );

        return "<{$element} ".implode(' ', $pairs).'>'.$this->lineEnd();
    }

    private function lineEnd(): string
    {
        $indent = $this->config['indent'] ?? self::DEFAULT_INDENT;

        return "\n".(is_string($indent) ? $indent : self::DEFAULT_INDENT);
    }

    private function sanitize(string|Stringable|int|float|null $text): string
    {
        // Only tag-shaped markup is stripped, so plain text such as "a < b" survives.
        $text = preg_replace('/<\/?[a-z!][^>]*>/i', ' ', (string) $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', double_encode: false);
    }

    private function limitFor(string $key): ?int
    {
        $limit = $this->config["{$key}_limit"] ?? null;

        return is_numeric($limit) ? (int) $limit : null;
    }

    /**
     * Truncate on a word boundary (multibyte-safe) and append an ellipsis.
     */
    private function limit(string $text, ?int $limit): string
    {
        if ($limit === null || $limit <= 0 || mb_strlen($text) <= $limit) {
            return $text;
        }

        $text = mb_substr($text, 0, max($limit - 3, 0));
        $space = mb_strrpos($text, ' ');

        if ($space) {
            $text = mb_substr($text, 0, $space);
        }

        return $text.'...';
    }

    /**
     * Build the URL of the current page for the given locale. By default the
     * default locale is served from the bare host (without `www.`), others from
     * a `{locale}.` subdomain; override with `locale_url` or `localizeUsing()`.
     */
    private function localizedUrl(string $locale): string
    {
        if ($this->localeResolver !== null) {
            return ($this->localeResolver)($locale, $this->request);
        }

        $template = $this->config['locale_url'] ?? null;

        if ($template instanceof Closure) {
            $resolved = $template($locale, $this->request);

            return is_scalar($resolved) ? (string) $resolved : '';
        }

        return strtr(is_string($template) && $template !== '' ? $template : self::DEFAULT_LOCALE_URL, [
            '[scheme]' => $this->request->getScheme(),
            '[locale]' => $locale === $this->defaultLocale ? '' : strtolower($locale).'.',
            '[lang]' => strtolower($locale),
            '[host]' => preg_replace('/^www\./i', '', $this->request->getHttpHost()) ?? '',
            '[uri]' => $this->request->getRequestUri(),
        ]);
    }

    private function configString(string $key, string $default = ''): string
    {
        $value = $this->config[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }
}
