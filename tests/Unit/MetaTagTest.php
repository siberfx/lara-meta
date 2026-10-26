<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Siberfx\LaraMeta\MetaTag;

final class MetaTagTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function metaTag(array $config = [], string $url = 'https://www.example.com/blog/post?page=2'): MetaTag
    {
        return new MetaTag(Request::create($url), $config, 'en');
    }

    #[Test]
    public function it_sets_defaults_from_config_and_request(): void
    {
        $meta = $this->metaTag(['title' => 'My Site']);

        $this->assertSame('My Site', $meta->get('title'));
        $this->assertSame('My Site', $meta->title);
        $this->assertSame('https://www.example.com/blog/post', $meta->get('url'));
    }

    #[Test]
    public function it_works_without_any_config(): void
    {
        $meta = $this->metaTag();

        $this->assertSame('', $meta->title);
        $this->assertSame([], $meta->locales);
        $this->assertSame("<link rel=\"canonical\" href=\"https://www.example.com/blog/post\">\n    ", $meta->canonical());
    }

    #[Test]
    public function it_sanitizes_values_when_setting(): void
    {
        $meta = $this->metaTag();

        $stored = $meta->set('description', "  <b>Hello</b>\n\n  \"world\"  ");

        $this->assertSame('Hello "world"', $stored);
        $this->assertSame('Hello "world"', $meta->get('description'));
        $this->assertSame('', $meta->set('empty', null));
        $this->assertSame('42', $meta->set('number', 42));
        $this->assertSame('str', $meta->set('stringable', Str::of('str')));
    }

    #[Test]
    public function it_reports_and_forgets_values(): void
    {
        $meta = $this->metaTag();
        $meta->set('robots', 'index,follow');

        $this->assertTrue($meta->has('robots'));
        $meta->forget('robots');
        $this->assertFalse($meta->has('robots'));
        $this->assertSame('fallback', $meta->get('robots', 'fallback'));
    }

    #[Test]
    public function it_limits_values_on_a_word_boundary(): void
    {
        $meta = $this->metaTag(['description_limit' => 20, 'title_limit' => 10]);

        $this->assertSame('The quick brown...', $meta->set('description', 'The quick brown fox jumps over the lazy dog'));
        $this->assertSame('Short', $meta->set('description', 'Short'));
        $this->assertSame('Hello...', $meta->set('title', 'Hello wonderful world'));
    }

    #[Test]
    public function it_limits_multibyte_text_without_breaking_characters(): void
    {
        $meta = $this->metaTag(['description_limit' => 12]);

        $value = $meta->set('description', 'Çağrı şöğüçİ ığüşöç');

        $this->assertSame('Çağrı...', $value);
        $this->assertTrue(mb_check_encoding($value, 'UTF-8'));
    }

    #[Test]
    public function it_renders_and_escapes_a_named_tag(): void
    {
        $meta = $this->metaTag();
        $meta->set('description', 'Fish & "Chips" <i>today</i>');

        $this->assertSame(
            "<meta name=\"description\" content=\"Fish &amp; &quot;Chips&quot; today\">\n    ",
            $meta->tag('description'),
        );
    }

    #[Test]
    public function an_explicit_tag_value_overrides_the_stored_one(): void
    {
        $meta = $this->metaTag();
        $meta->set('image', 'https://example.com/a.png');

        $this->assertStringContainsString('content="https://example.com/b.png"', $meta->tag('image', 'https://example.com/b.png'));
        $this->assertStringContainsString('content="https://example.com/a.png"', $meta->tag('image'));
        $this->assertStringContainsString('content=""', $meta->tag('missing'));
    }

    #[Test]
    public function it_does_not_double_encode_existing_entities(): void
    {
        $meta = $this->metaTag();

        $this->assertStringContainsString('content="A &amp; B"', $meta->tag('x', 'A &amp; B'));
    }

    #[Test]
    public function it_renders_canonical_and_hreflang_alternates(): void
    {
        $meta = $this->metaTag(['locales' => ['en', 'TR']]);

        $html = $meta->canonical();

        $this->assertStringContainsString('<link rel="canonical" href="https://www.example.com/blog/post">', $html);
        $this->assertStringContainsString('hreflang="en" href="https://example.com/blog/post?page=2"', $html);
        $this->assertStringContainsString('hreflang="TR" href="https://tr.example.com/blog/post?page=2"', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="https://example.com/blog/post?page=2">', $html);
        $this->assertStringNotContainsString('<meta rel=', $html);
    }

    #[Test]
    public function it_accepts_locales_from_a_callable_and_setter(): void
    {
        $meta = $this->metaTag(['locales' => static fn (): array => ['de', 'fr']]);
        $this->assertSame(['de', 'fr'], $meta->locales);

        $meta->setLocales(['nl']);
        $this->assertSame(['nl'], $meta->locales);
    }

    #[Test]
    public function it_uses_a_custom_locale_url_template(): void
    {
        $meta = $this->metaTag([
            'locales' => ['tr'],
            'locale_url' => '[scheme]://[host]/[locale][uri]',
        ], 'http://localhost/about');

        $this->assertStringContainsString('href="http://localhost/tr./about"', $meta->canonical());
    }

    #[Test]
    public function it_renders_open_graph_tags(): void
    {
        $meta = $this->metaTag(['open_graph' => ['site_name' => 'Acme', 'type' => 'website']]);
        $meta->set('title', 'Home');
        $meta->set('image', 'https://example.com/og.png');

        $html = $meta->openGraph();

        $this->assertStringStartsWith('<meta property="og:url" content="https://www.example.com/blog/post">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Home">', $html);
        $this->assertStringContainsString('<meta property="og:site_name" content="Acme">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://example.com/og.png">', $html);
        $this->assertStringNotContainsString('og:video', $html);
        $this->assertSame(1, substr_count($html, 'og:url'));
    }

    #[Test]
    public function configured_open_graph_values_win_over_runtime_values(): void
    {
        $meta = $this->metaTag(['open_graph' => ['type' => 'website']]);
        $meta->set('type', 'article');

        $this->assertStringContainsString('og:type" content="website"', $meta->openGraph());
    }

    #[Test]
    public function it_renders_twitter_card_tags_with_fallbacks(): void
    {
        $meta = $this->metaTag(['twitter' => ['card' => 'summary', 'site' => '@acme']]);
        $meta->set('image', 'https://example.com/card.png');

        $html = $meta->twitterCard();

        $this->assertStringContainsString('<meta name="twitter:card" content="summary">', $html);
        $this->assertStringContainsString('<meta name="twitter:site" content="@acme">', $html);
        $this->assertStringContainsString('<meta name="twitter:image:src" content="https://example.com/card.png">', $html);
        $this->assertStringContainsString('<meta name="twitter:domain" content="www.example.com">', $html);
    }

    #[Test]
    public function configured_twitter_values_override_fallbacks(): void
    {
        $meta = $this->metaTag(['twitter' => ['domain' => 'acme.test', 'image' => 'https://acme.test/x.png', 'image:src' => 'https://acme.test/x.png']]);
        $meta->set('image', 'https://example.com/ignored.png');

        $html = $meta->twitterCard();

        $this->assertStringContainsString('twitter:domain" content="acme.test"', $html);
        $this->assertStringContainsString('twitter:image:src" content="https://acme.test/x.png"', $html);
        $this->assertStringNotContainsString('ignored.png', $html);
    }

    #[Test]
    public function it_renders_the_facebook_app_id(): void
    {
        $meta = $this->metaTag();
        $meta->set('fb:app_id', '123456');

        $this->assertSame("<meta property=\"fb:app_id\" content=\"123456\">\n    ", $meta->fbAppId());
    }

    #[Test]
    public function x_default_can_be_disabled(): void
    {
        $meta = $this->metaTag(['locales' => ['en'], 'x_default' => false]);

        $this->assertStringNotContainsString('x-default', $meta->canonical());
    }

    #[Test]
    public function canonical_and_open_graph_honour_a_url_set_at_runtime(): void
    {
        $meta = $this->metaTag();
        $meta->set('url', 'https://example.com/canonical-page');

        $this->assertStringContainsString('href="https://example.com/canonical-page"', $meta->canonical());
        $this->assertStringContainsString('og:url" content="https://example.com/canonical-page"', $meta->openGraph());
    }

    #[Test]
    public function the_facebook_app_id_is_skipped_when_unset(): void
    {
        $this->assertSame('', $this->metaTag()->fbAppId());
    }

    #[Test]
    public function it_resolves_alternates_with_a_closure_or_path_template(): void
    {
        $meta = $this->metaTag(['locales' => ['tr'], 'x_default' => false, 'locale_url' => '[scheme]://[host]/[lang][uri]'], 'https://www.example.co.uk/about');
        $this->assertStringContainsString('href="https://example.co.uk/tr/about"', $meta->canonical());

        $meta = $this->metaTag(['locales' => ['tr'], 'x_default' => false, 'locale_url' => static fn (string $l, Request $r): string => "https://x.test/{$l}".$r->getRequestUri()], 'https://example.com/about');
        $this->assertStringContainsString('href="https://x.test/tr/about"', $meta->canonical());

        $meta->localizeUsing(static fn (string $l): string => "https://y.test/{$l}");
        $this->assertStringContainsString('href="https://y.test/tr"', $meta->canonical());
    }

    #[Test]
    public function plain_text_comparisons_survive_sanitising(): void
    {
        $this->assertSame('if a < b and c > d', $this->metaTag()->set('description', 'if a < b and c > d'));
    }

    #[Test]
    public function it_renders_the_title_with_an_optional_suffix(): void
    {
        $meta = $this->metaTag(['title' => 'My Site', 'title_suffix' => 'My Site']);
        $this->assertSame('<title>My Site</title>
    ', $meta->titleTag());

        $meta->set('title', 'Fish & Chips');
        $this->assertSame('Fish & Chips | My Site', $meta->fullTitle());
        $this->assertStringContainsString('<title>Fish &amp; Chips | My Site</title>', $meta->titleTag());

        $this->assertSame('', $this->metaTag()->titleTag());
    }

    #[Test]
    public function it_sets_robots_directives(): void
    {
        $meta = $this->metaTag();

        $meta->noindex();
        $this->assertSame('noindex,nofollow', $meta->get('robots'));
        $meta->noindex(follow: true);
        $this->assertSame('noindex,follow', $meta->get('robots'));
        $meta->index();
        $this->assertSame('index,follow', $meta->get('robots'));
    }

    #[Test]
    public function it_renders_json_ld_safely(): void
    {
        $meta = $this->metaTag();
        $this->assertSame('', $meta->jsonLd());

        $meta->addJsonLd(['@type' => 'Article', 'headline' => 'Hi </script><script>alert(1)</script> çağrı']);
        $html = $meta->jsonLd();

        $this->assertStringContainsString('<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article"', $html);
        $this->assertStringNotContainsString('</script><script>', $html);
        $this->assertStringContainsString('çağrı', $html);
    }

    #[Test]
    public function it_renders_extended_open_graph_and_twitter_tags(): void
    {
        $meta = $this->metaTag();
        $meta->set('image', 'https://example.com/a.png');
        $meta->set('image:width', 1200);
        $meta->set('image:alt', 'A picture');
        $meta->set('article:published_time', '2026-01-01T00:00:00Z');

        $og = $meta->openGraph();
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $og);
        $this->assertStringContainsString('<meta property="og:image:alt" content="A picture">', $og);
        $this->assertStringContainsString('<meta property="article:published_time" content="2026-01-01T00:00:00Z">', $og);

        $twitter = $meta->twitterCard();
        $this->assertStringContainsString('<meta name="twitter:image" content="https://example.com/a.png">', $twitter);
        $this->assertStringContainsString('<meta name="twitter:image:alt" content="A picture">', $twitter);
    }

    #[Test]
    public function it_renders_the_whole_head_block_and_compact_output(): void
    {
        $meta = $this->metaTag(['title' => 'Home', 'indent' => '']);
        $meta->set('description', 'Hello');
        $meta->noindex();

        $html = $meta->render()->toHtml();

        $this->assertStringStartsWith('<title>Home</title>
<meta name="description" content="Hello">
<meta name="robots"', $html);
        $this->assertStringContainsString('<link rel="canonical"', $html);
        $this->assertStringContainsString('og:title', $html);
        $this->assertSame($html, $meta->toHtml());
    }

    #[Test]
    public function identical_json_ld_documents_are_added_once(): void
    {
        $meta = $this->metaTag();
        $meta->addJsonLd(['@type' => 'WebSite']);
        $meta->addJsonLd(['@type' => 'WebSite']);

        $this->assertCount(1, $meta->jsonLd);
    }
}
