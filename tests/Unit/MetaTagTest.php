<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Siberfx\LaraMeta\MetaTag;

it('sets defaults from config and request', function (): void {
    $meta = metaTag(['title' => 'My Site']);

    expect($meta->get('title'))->toBe('My Site')
        ->and($meta->title)->toBe('My Site')
        ->and($meta->get('url'))->toBe('https://www.example.com/blog/post');
});

it('works without any config', function (): void {
    $meta = metaTag();

    expect($meta->title)->toBe('')
        ->and($meta->locales)->toBe([])
        ->and($meta->canonical())->toBe("<link rel=\"canonical\" href=\"https://www.example.com/blog/post\">\n    ");
});

it('sanitizes values when setting', function (): void {
    $meta = metaTag();

    expect($meta->set('description', "  <b>Hello</b>\n\n  \"world\"  "))->toBe('Hello "world"')
        ->and($meta->get('description'))->toBe('Hello "world"')
        ->and($meta->set('empty', null))->toBe('')
        ->and($meta->set('number', 42))->toBe('42')
        ->and($meta->set('stringable', Str::of('str')))->toBe('str');
});

it('reports and forgets values', function (): void {
    $meta = metaTag();
    $meta->set('robots', 'index,follow');

    expect($meta->has('robots'))->toBeTrue();

    $meta->forget('robots');

    expect($meta->has('robots'))->toBeFalse()
        ->and($meta->get('robots', 'fallback'))->toBe('fallback');
});

it('limits values on a word boundary', function (): void {
    $meta = metaTag(['description_limit' => 20, 'title_limit' => 10]);

    expect($meta->set('description', 'The quick brown fox jumps over the lazy dog'))->toBe('The quick brown...')
        ->and($meta->set('description', 'Short'))->toBe('Short')
        ->and($meta->set('title', 'Hello wonderful world'))->toBe('Hello...');
});

it('limits multibyte text without breaking characters', function (): void {
    $value = metaTag(['description_limit' => 12])->set('description', 'Çağrı şöğüçİ ığüşöç');

    expect($value)->toBe('Çağrı...')
        ->and(mb_check_encoding($value, 'UTF-8'))->toBeTrue();
});

it('renders and escapes a named tag', function (): void {
    $meta = metaTag();
    $meta->set('description', 'Fish & "Chips" <i>today</i>');

    expect($meta->tag('description'))
        ->toBe("<meta name=\"description\" content=\"Fish &amp; &quot;Chips&quot; today\">\n    ");
});

test('an explicit tag value overrides the stored one', function (): void {
    $meta = metaTag();
    $meta->set('image', 'https://example.com/a.png');

    expect($meta->tag('image', 'https://example.com/b.png'))->toContain('content="https://example.com/b.png"')
        ->and($meta->tag('image'))->toContain('content="https://example.com/a.png"')
        ->and($meta->tag('missing'))->toContain('content=""');
});

it('does not double encode existing entities', function (): void {
    expect(metaTag()->tag('x', 'A &amp; B'))->toContain('content="A &amp; B"');
});

it('renders canonical and hreflang alternates', function (): void {
    expect(metaTag(['locales' => ['en', 'TR']])->canonical())
        ->toContain(
            '<link rel="canonical" href="https://www.example.com/blog/post">',
            'hreflang="en" href="https://example.com/blog/post?page=2"',
            'hreflang="TR" href="https://tr.example.com/blog/post?page=2"',
            '<link rel="alternate" hreflang="x-default" href="https://example.com/blog/post?page=2">',
        )
        ->not->toContain('<meta rel=');
});

it('accepts locales from a callable and setter', function (): void {
    $meta = metaTag(['locales' => static fn (): array => ['de', 'fr']]);

    expect($meta->locales)->toBe(['de', 'fr']);

    $meta->setLocales(['nl']);

    expect($meta->locales)->toBe(['nl']);
});

it('uses a custom locale url template', function (): void {
    $meta = metaTag([
        'locales' => ['tr'],
        'locale_url' => '[scheme]://[host]/[locale][uri]',
    ], 'http://localhost/about');

    expect($meta->canonical())->toContain('href="http://localhost/tr./about"');
});

it('renders open graph tags', function (): void {
    $meta = metaTag(['open_graph' => ['site_name' => 'Acme', 'type' => 'website']]);
    $meta->set('title', 'Home');
    $meta->set('image', 'https://example.com/og.png');

    $html = $meta->openGraph();

    expect($html)
        ->toStartWith('<meta property="og:url" content="https://www.example.com/blog/post">')
        ->toContain(
            '<meta property="og:title" content="Home">',
            '<meta property="og:site_name" content="Acme">',
            '<meta property="og:type" content="website">',
            '<meta property="og:image" content="https://example.com/og.png">',
        )
        ->not->toContain('og:video')
        ->and(substr_count($html, 'og:url'))->toBe(1);
});

test('configured open graph values win over runtime values', function (): void {
    $meta = metaTag(['open_graph' => ['type' => 'website']]);
    $meta->set('type', 'article');

    expect($meta->openGraph())->toContain('og:type" content="website"');
});

it('renders twitter card tags with fallbacks', function (): void {
    $meta = metaTag(['twitter' => ['card' => 'summary', 'site' => '@acme']]);
    $meta->set('image', 'https://example.com/card.png');

    expect($meta->twitterCard())->toContain(
        '<meta name="twitter:card" content="summary">',
        '<meta name="twitter:site" content="@acme">',
        '<meta name="twitter:image:src" content="https://example.com/card.png">',
        '<meta name="twitter:domain" content="www.example.com">',
    );
});

test('configured twitter values override fallbacks', function (): void {
    $meta = metaTag(['twitter' => ['domain' => 'acme.test', 'image' => 'https://acme.test/x.png', 'image:src' => 'https://acme.test/x.png']]);
    $meta->set('image', 'https://example.com/ignored.png');

    expect($meta->twitterCard())
        ->toContain('twitter:domain" content="acme.test"', 'twitter:image:src" content="https://acme.test/x.png"')
        ->not->toContain('ignored.png');
});

it('renders the facebook app id', function (): void {
    $meta = metaTag();
    $meta->set('fb:app_id', '123456');

    expect($meta->fbAppId())->toBe("<meta property=\"fb:app_id\" content=\"123456\">\n    ");
});

test('x-default can be disabled', function (): void {
    expect(metaTag(['locales' => ['en'], 'x_default' => false])->canonical())->not->toContain('x-default');
});

test('canonical and open graph honour a url set at runtime', function (): void {
    $meta = metaTag();
    $meta->set('url', 'https://example.com/canonical-page');

    expect($meta->canonical())->toContain('href="https://example.com/canonical-page"')
        ->and($meta->openGraph())->toContain('og:url" content="https://example.com/canonical-page"');
});

test('the facebook app id is skipped when unset', function (): void {
    expect(metaTag()->fbAppId())->toBe('');
});

it('resolves alternates with a closure or path template', function (): void {
    $meta = metaTag(['locales' => ['tr'], 'x_default' => false, 'locale_url' => '[scheme]://[host]/[lang][uri]'], 'https://www.example.co.uk/about');
    expect($meta->canonical())->toContain('href="https://example.co.uk/tr/about"');

    $meta = metaTag(['locales' => ['tr'], 'x_default' => false, 'locale_url' => static fn (string $l, Request $r): string => "https://x.test/{$l}".$r->getRequestUri()], 'https://example.com/about');
    expect($meta->canonical())->toContain('href="https://x.test/tr/about"');

    $meta->localizeUsing(static fn (string $l): string => "https://y.test/{$l}");
    expect($meta->canonical())->toContain('href="https://y.test/tr"');
});

test('plain text comparisons survive sanitising', function (): void {
    expect(metaTag()->set('description', 'if a < b and c > d'))->toBe('if a < b and c > d');
});

it('renders the title with an optional suffix', function (): void {
    $meta = metaTag(['title' => 'My Site', 'title_suffix' => 'My Site']);
    expect($meta->titleTag())->toBe("<title>My Site</title>\n    ");

    $meta->set('title', 'Fish & Chips');
    expect($meta->fullTitle())->toBe('Fish & Chips | My Site')
        ->and($meta->titleTag())->toContain('<title>Fish &amp; Chips | My Site</title>')
        ->and(metaTag()->titleTag())->toBe('');
});

it('sets robots directives', function (): void {
    $meta = metaTag();

    $meta->noindex();
    expect($meta->get('robots'))->toBe('noindex,nofollow');

    $meta->noindex(follow: true);
    expect($meta->get('robots'))->toBe('noindex,follow');

    $meta->index();
    expect($meta->get('robots'))->toBe('index,follow');
});

it('renders json-ld safely', function (): void {
    $meta = metaTag();
    expect($meta->jsonLd())->toBe('');

    $meta->addJsonLd(['@type' => 'Article', 'headline' => 'Hi </script><script>alert(1)</script> çağrı']);

    expect($meta->jsonLd())
        ->toContain('<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article"', 'çağrı')
        ->not->toContain('</script><script>');
});

it('renders extended open graph and twitter tags', function (): void {
    $meta = metaTag();
    $meta->set('image', 'https://example.com/a.png');
    $meta->set('image:width', 1200);
    $meta->set('image:alt', 'A picture');
    $meta->set('article:published_time', '2026-01-01T00:00:00Z');

    expect($meta->openGraph())->toContain(
        '<meta property="og:image:width" content="1200">',
        '<meta property="og:image:alt" content="A picture">',
        '<meta property="article:published_time" content="2026-01-01T00:00:00Z">',
    )->and($meta->twitterCard())->toContain(
        '<meta name="twitter:image" content="https://example.com/a.png">',
        '<meta name="twitter:image:alt" content="A picture">',
    );
});

it('renders the whole head block and compact output', function (): void {
    $meta = metaTag(['title' => 'Home', 'indent' => '']);
    $meta->set('description', 'Hello');
    $meta->noindex();

    $html = $meta->render()->toHtml();

    expect($html)
        ->toStartWith("<title>Home</title>\n<meta name=\"description\" content=\"Hello\">\n<meta name=\"robots\"")
        ->toContain('<link rel="canonical"', 'og:title')
        ->toBe($meta->toHtml());
});

test('identical json-ld documents are added once', function (): void {
    $meta = metaTag();
    $meta->addJsonLd(['@type' => 'WebSite']);
    $meta->addJsonLd(['@type' => 'WebSite']);

    expect($meta->jsonLd)->toHaveCount(1);
});

it('exposes read-only properties', function (): void {
    $meta = metaTag(['title' => 'Home']);
    $meta->set('description', 'Hello');

    expect($meta->metas)->toHaveKey('description', 'Hello')
        ->and(isset($meta->metas, $meta->locales, $meta->jsonLd, $meta->title))->toBeTrue()
        ->and(isset($meta->missing))->toBeFalse();
});

test('read-only properties cannot be modified', function (string $property): void {
    $meta = metaTag();

    expect(fn () => $meta->{$property} = [])
        ->toThrow(Error::class, 'Cannot modify read-only property '.MetaTag::class.'::$'.$property);
})->with(['metas', 'locales', 'jsonLd', 'title']);

test('undefined properties cannot be read or created', function (string $property): void {
    $meta = metaTag();

    expect(fn () => $meta->{$property} = 'x')->toThrow(Error::class, 'Cannot create dynamic property')
        ->and(fn () => $meta->{$property})->toThrow(Error::class, 'Undefined property');
})->with(['missing']);
