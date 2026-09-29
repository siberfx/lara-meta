<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta;

use Illuminate\Http\Request;
use Siberfx\LaraMeta\Enums\OpenGraphTag;
use Siberfx\LaraMeta\Enums\TwitterCardTag;
use Stringable;

/**
 * Collects page meta data during a request and renders it as HTML meta tags.
 *
 * Values are sanitised when stored (markup stripped, whitespace collapsed,
 * length-limited per config) and HTML-escaped when rendered.
 */
class MetaTag
{
    public const string DEFAULT_LOCALE_URL = '[scheme]://[locale][host][uri]';

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
     * The current page title (shortcut for `get('title', '')`).
     */
    public string $title {
        get => $this->get('title', '');
    }

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
     * Render a `<meta name="…">` tag, using the stored value unless one is given.
     */
    public function tag(string $key, string|Stringable|null $value = ''): string
    {
        $content = (string) $value;

        return $this->createTag([
            'name' => $key,
            'content' => $content !== '' ? $content : $this->get($key, ''),
        ]);
    }

    /**
     * Render the canonical link plus one `hreflang` alternate per locale.
     */
    public function canonical(): string
    {
        $html = $this->createTag(['rel' => 'canonical', 'href' => $this->request->url()]);

        foreach ($this->locales as $locale) {
            $html .= $this->createTag([
                'rel' => 'alternate',
                'hreflang' => $locale,
                'href' => $this->localizedUrl($locale),
            ]);
        }

        return $html;
    }

    public function openGraph(): string
    {
        $html = [
            OpenGraphTag::Url->value => $this->createTag([
                'property' => OpenGraphTag::Url->property(),
                'content' => $this->request->url(),
            ]),
        ];

        foreach (OpenGraphTag::cases() as $tag) {
            $value = $this->resolve('open_graph', $tag->value);

            if ($value !== '') {
                $html[$tag->value] = $this->createTag(['property' => $tag->property(), 'content' => $value]);
            }
        }

        return implode('', $html);
    }

    public function fbAppId(): string
    {
        return $this->createTag([
            'property' => 'fb:app_id',
            'content' => $this->get('fb:app_id', ''),
        ]);
    }

    public function twitterCard(): string
    {
        $fallbacks = [
            TwitterCardTag::ImageSrc->value => $this->get('image', ''),
            TwitterCardTag::Domain->value => $this->request->getHttpHost(),
        ];

        $html = [];

        foreach (TwitterCardTag::cases() as $tag) {
            $value = $this->resolve('twitter', $tag->value);
            $value = $value !== '' ? $value : ($fallbacks[$tag->value] ?? '');

            if ($value !== '') {
                $html[] = $this->createTag(['name' => $tag->property(), 'content' => $value]);
            }
        }

        return implode('', $html);
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
    private function createTag(array $attributes): string
    {
        $pairs = array_map(
            fn (string $name, string $value): string => sprintf('%s="%s"', $name, $this->escape($this->sanitize($value))),
            array_keys($attributes),
            $attributes,
        );

        return '<meta '.implode(' ', $pairs).">\n    ";
    }

    private function sanitize(string|Stringable|int|float|null $text): string
    {
        $text = preg_replace('/<[^>]+>/', ' ', (string) $text) ?? '';

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
     * Build the URL of the current page for the given locale. The default
     * locale is served from the bare host, others from a `{locale}.` subdomain.
     */
    private function localizedUrl(string $locale): string
    {
        $hostParts = explode('.', $this->request->getHttpHost());

        return strtr($this->configString('locale_url', self::DEFAULT_LOCALE_URL), [
            '[scheme]' => $this->request->getScheme(),
            '[locale]' => $locale === $this->defaultLocale ? '' : strtolower($locale).'.',
            '[host]' => implode('.', array_slice($hostParts, -2)),
            '[uri]' => $this->request->getRequestUri(),
        ]);
    }

    private function configString(string $key, string $default = ''): string
    {
        $value = $this->config[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }
}
