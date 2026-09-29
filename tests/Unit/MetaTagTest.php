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
        $this->assertSame("<meta rel=\"canonical\" href=\"https://www.example.com/blog/post\">\n    ", $meta->canonical());
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

        $this->assertStringContainsString('<meta rel="canonical" href="https://www.example.com/blog/post">', $html);
        $this->assertStringContainsString('hreflang="en" href="https://example.com/blog/post?page=2"', $html);
        $this->assertStringContainsString('hreflang="TR" href="https://tr.example.com/blog/post?page=2"', $html);
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
        $meta = $this->metaTag(['twitter' => ['domain' => 'acme.test', 'image:src' => 'https://acme.test/x.png']]);
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
}
