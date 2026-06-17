<?php

namespace Siberfx\LaraMeta;

use Illuminate\Support\ServiceProvider;

class MetaTagsServiceProvider extends ServiceProvider
{
    /**
     * Path to the packaged configuration file.
     *
     * @var string
     */
    protected string $configPath = __DIR__ . '/../../config/config.php';

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            $this->configPath => config_path('meta-tags.php'),
        ], 'meta-tags-config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        // Merge the package config so values are available out of the box,
        // even before the user publishes their own copy.
        $this->mergeConfigFrom($this->configPath, 'meta-tags');

        $this->app->singleton('metatag', function ($app) {
            return new MetaTag(
                $app['request'],
                $app['config']->get('meta-tags', []),
                $app['config']->get('app.locale', 'en')
            );
        });

        $this->app->alias('metatag', MetaTag::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['metatag', MetaTag::class];
    }
}
