<?php

namespace Siberfx\LaraMeta;

use Illuminate\Support\ServiceProvider;

class MetaTagsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;
    
    protected $packageName = 'LaraMeta';

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/config.php' => config_path('meta-tags.php')
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // $this->app['metatag'] = $this->app->share(function ($app) { // Removed to support laravel 5.4
       $this->app->singleton('metatag', function ($app) {
            return new MetaTag(
                app()->request(),
                config('meta-tags'),
                config('app.locale')
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['metatag'];
    }
}
