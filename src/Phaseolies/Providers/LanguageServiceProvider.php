<?php

namespace Phaseolies\Providers;

use Phaseolies\Support\Facades\Lang;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Translation\Translator;
use Phaseolies\Providers\ServiceProvider;

class LanguageServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('translation.loader', function ($app) {
            return new FileLoader($app['path.lang']);
        });

        $this->app->singleton('translator', function ($app) {
            $locale = 'en';
            $fallback = 'en';

            if ($app->has('config')) {
                $config = $app['config'];
                $locale = method_exists($config, 'get')
                    ? $config->get('app.locale', 'en')
                    : 'en';

                $fallback = method_exists($config, 'get')
                    ? $config->get('app.fallback_locale', 'en')
                    : 'en';
            }

            $translator = new Translator(
                $app['translation.loader'],
                $locale
            );
            $translator->setFallback($fallback);

            return $translator;
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Lang::setFacadeApplication($this->app);
    }
}
