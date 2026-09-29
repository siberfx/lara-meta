<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

class MetaTagsServiceProvider extends ServiceProvider
{
    public const string CONFIG_KEY = 'meta-tags';

    private const string CONFIG_PATH = __DIR__.'/../../config/config.php';

    private const string MIDDLEWARE_STUB = __DIR__.'/../../stubs/DefaultMetaTags.php.stub';

    #[Override]
    public function register(): void
    {
        // Merged so the package works out of the box, before the config is published.
        $this->mergeConfigFrom(self::CONFIG_PATH, self::CONFIG_KEY);

        // Scoped rather than singleton: meta data belongs to a single request, and
        // Octane / queue workers must not leak it (or a stale Request) between requests.
        $this->app->scoped(MetaTag::class, static fn (Application $app): MetaTag => new MetaTag(
            request: $app->make('request'),
            config: $app->make('config')->array(self::CONFIG_KEY, []),
            defaultLocale: $app->make('config')->string('app.locale', 'en'),
        ));

        $this->app->alias(MetaTag::class, 'metatag');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $config = [self::CONFIG_PATH => config_path(self::CONFIG_KEY.'.php')];
        $middleware = [self::MIDDLEWARE_STUB => app_path('Http/Middleware/DefaultMetaTags.php')];

        $this->publishes($config, 'meta-tags-config');
        $this->publishes($middleware, 'meta-tags-middleware');
        $this->publishes([...$config, ...$middleware], 'meta-tags');
    }
}
