<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Siberfx\LaraMeta\Facades\MetaTag as MetaTagFacade;
use Siberfx\LaraMeta\MetaTag;
use Siberfx\LaraMeta\MetaTagsServiceProvider;
use Siberfx\LaraMeta\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_package_config_without_publishing(): void
    {
        $this->assertSame(70, config('meta-tags.title_limit'));
        $this->assertSame(['en', 'tr'], config('meta-tags.locales'));
    }

    #[Test]
    public function it_resolves_the_same_instance_by_class_alias_and_facade(): void
    {
        $instance = app()->make(MetaTag::class);

        $this->assertSame($instance, app()->make('metatag'));
        $this->assertSame($instance, MetaTagFacade::getFacadeRoot());
    }

    #[Test]
    public function the_instance_is_scoped_to_a_single_request(): void
    {
        MetaTagFacade::set('description', 'first request');

        app()->forgetScopedInstances();
        MetaTagFacade::clearResolvedInstances();

        $this->assertNull(MetaTagFacade::get('description'));
    }

    #[Test]
    public function it_uses_the_application_locale_as_default_locale(): void
    {
        config(['app.locale' => 'tr']);
        app()->forgetScopedInstances();

        $this->assertSame('tr', app()->make(MetaTag::class)->defaultLocale);
    }

    #[Test]
    public function it_renders_through_the_facade_alias_in_blade(): void
    {
        MetaTagFacade::set('description', 'Rendered in Blade');

        $html = Blade::render("{!! MetaTag::tag('description') !!}");

        $this->assertStringContainsString('content="Rendered in Blade"', $html);
    }

    #[Test]
    public function it_registers_the_config_for_publishing(): void
    {
        $paths = MetaTagsServiceProvider::pathsToPublish(MetaTagsServiceProvider::class, 'meta-tags-config');

        $this->assertCount(1, $paths);
        $this->assertContains(config_path('meta-tags.php'), $paths);
    }

    #[Test]
    public function it_registers_the_middleware_for_publishing(): void
    {
        $paths = MetaTagsServiceProvider::pathsToPublish(MetaTagsServiceProvider::class, 'meta-tags-middleware');

        $this->assertSame([app_path('Http/Middleware/DefaultMetaTags.php')], array_values($paths));
        $this->assertFileExists((string) array_key_first($paths));
    }

    #[Test]
    public function the_combined_tag_publishes_config_and_middleware(): void
    {
        $paths = MetaTagsServiceProvider::pathsToPublish(MetaTagsServiceProvider::class, 'meta-tags');

        $this->assertEqualsCanonicalizing(
            [config_path('meta-tags.php'), app_path('Http/Middleware/DefaultMetaTags.php')],
            array_values($paths),
        );
    }

    #[Test]
    public function the_published_middleware_sets_defaults_that_controllers_can_override(): void
    {
        $stub = (string) array_key_first(
            MetaTagsServiceProvider::pathsToPublish(MetaTagsServiceProvider::class, 'meta-tags-middleware'),
        );
        $target = (string) tempnam(sys_get_temp_dir(), 'meta');
        copy($stub, $target);
        require_once $target;

        $middleware = new \App\Http\Middleware\DefaultMetaTags;
        $response = $middleware->handle(Request::create('/'), static function (): Response {
            MetaTagFacade::set('description', 'Set by the controller');

            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
        $this->assertSame('Set by the controller', MetaTagFacade::get('description'));
        $this->assertStringEndsWith('images/share-default.png', (string) MetaTagFacade::get('image'));
        $this->assertSame('noindex,nofollow', MetaTagFacade::get('robots'));

        @unlink($target);
    }
}
