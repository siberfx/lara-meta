<?php

declare(strict_types=1);

use App\Http\Middleware\DefaultMetaTags;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Siberfx\LaraMeta\Facades\MetaTag as MetaTagFacade;
use Siberfx\LaraMeta\Http\Middleware\InjectDefaultMetaTags;
use Siberfx\LaraMeta\MetaTag;
use Siberfx\LaraMeta\MetaTagsServiceProvider;

/**
 * @return array<string, string>
 */
function publishPaths(string $tag): array
{
    return MetaTagsServiceProvider::pathsToPublish(MetaTagsServiceProvider::class, $tag);
}

it('merges the package config without publishing', function (): void {
    expect(config('meta-tags.title_limit'))->toBe(70)
        ->and(config('meta-tags.locales'))->toBe(['en', 'tr']);
});

it('resolves the same instance by class, alias and facade', function (): void {
    $instance = app()->make(MetaTag::class);

    expect(app()->make('metatag'))->toBe($instance)
        ->and(MetaTagFacade::getFacadeRoot())->toBe($instance);
});

test('the instance is scoped to a single request', function (): void {
    MetaTagFacade::set('description', 'first request');

    app()->forgetScopedInstances();
    MetaTagFacade::clearResolvedInstances();

    expect(MetaTagFacade::get('description'))->toBeNull();
});

it('uses the application locale as default locale', function (): void {
    config(['app.locale' => 'tr']);
    app()->forgetScopedInstances();

    expect(app()->make(MetaTag::class)->defaultLocale)->toBe('tr');
});

it('renders through the facade alias in blade', function (): void {
    MetaTagFacade::set('description', 'Rendered in Blade');

    expect(Blade::render("{!! MetaTag::tag('description') !!}"))->toContain('content="Rendered in Blade"');
});

it('renders through the blade directive and component', function (): void {
    MetaTagFacade::set('title', 'Directive & Co');

    expect(Blade::render('@metaTags'))->toContain('<title>Directive &amp; Co</title>', '<link rel="canonical"')
        ->and(Blade::render('<x-meta-tags />'))->toContain('<title>Directive &amp; Co</title>');
});

it('registers the config for publishing', function (): void {
    expect(publishPaths('meta-tags-config'))->toHaveCount(1)->toContain(config_path('meta-tags.php'));
});

it('registers the middleware for publishing', function (): void {
    $paths = publishPaths('meta-tags-middleware');

    expect(array_values($paths))->toBe([app_path('Http/Middleware/DefaultMetaTags.php')])
        ->and((string) array_key_first($paths))->toBeFile();
});

test('the combined tag publishes config and middleware', function (): void {
    expect(array_values(publishPaths('meta-tags')))
        ->toEqualCanonicalizing([config_path('meta-tags.php'), app_path('Http/Middleware/DefaultMetaTags.php')]);
});

test('the published middleware sets defaults that controllers can override', function (): void {
    $target = (string) tempnam(sys_get_temp_dir(), 'meta');
    copy((string) array_key_first(publishPaths('meta-tags-middleware')), $target);
    require_once $target;

    $response = (new DefaultMetaTags)->handle(Request::create('/'), static function (): Response {
        MetaTagFacade::set('description', 'Set by the controller');

        return new Response('ok');
    });

    expect($response->getContent())->toBe('ok')
        ->and(MetaTagFacade::get('description'))->toBe('Set by the controller')
        ->and((string) MetaTagFacade::get('image'))->toEndWith('images/share-default.png')
        ->and(MetaTagFacade::get('robots'))->toBe('noindex,nofollow')
        ->and(MetaTagFacade::jsonLd())->toContain('"@type":"WebSite"');

    @unlink($target);
});

test('the default middleware is registered on the web group', function (): void {
    $groups = app(HttpKernel::class)->getMiddlewareGroups();

    expect((array) ($groups['web'] ?? []))->toContain(InjectDefaultMetaTags::class);
});

it('injects defaults and json-ld into web responses', function (): void {
    config(['meta-tags.defaults' => ['description' => 'Default description']]);

    Route::middleware('web')->get('/auto', static fn (): string => (string) MetaTagFacade::render());

    expect((string) $this->get('/auto')->assertOk()->getContent())
        ->toContain('content="Default description"', '"@type":"WebSite"');
});

test('the automatic middleware can be disabled', function (): void {
    config(['meta-tags.auto_middleware' => false]);
    $kernel = new Kernel(app(), app('router'));
    app()->instance(HttpKernel::class, $kernel);

    (new MetaTagsServiceProvider(app()))->boot();

    expect((array) ($kernel->getMiddlewareGroups()['web'] ?? []))->not->toContain(InjectDefaultMetaTags::class);
});
