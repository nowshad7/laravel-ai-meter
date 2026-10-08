<?php

namespace Nsd7\AiMeter;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Nsd7\AiMeter\Budget\DatabaseSpendStore;
use Nsd7\AiMeter\Console\PruneCommand;
use Nsd7\AiMeter\Core\Contracts\PriceProvider;
use Nsd7\AiMeter\Core\Contracts\Recorder;
use Nsd7\AiMeter\Core\Contracts\Redactor;
use Nsd7\AiMeter\Core\Contracts\SpendStore;
use Nsd7\AiMeter\Core\Pricing\ModelPrice;
use Nsd7\AiMeter\Core\Pricing\PriceBook;
use Nsd7\AiMeter\Http\Middleware\EnforceBudget;
use Nsd7\AiMeter\Recording\DatabaseRecorder;
use Nsd7\AiMeter\Recording\Redaction\DefaultRedactor;

class AiMeterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-meter.php', 'ai-meter');

        $this->app->singleton(Redactor::class, DefaultRedactor::class);
        $this->app->singleton(PriceProvider::class, fn () => $this->makePriceBook());
        $this->app->singleton(Recorder::class, fn ($app) => new DatabaseRecorder($app->make(Redactor::class)));
        $this->app->singleton(SpendStore::class, DatabaseSpendStore::class);

        $this->app->singleton(Meter::class, fn ($app) => new Meter(
            $app->make(PriceProvider::class),
            $app->make(Recorder::class),
            $app->make(SpendStore::class),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-meter');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-meter');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('ai.budget', EnforceBudget::class);

        if (config('ai-meter.enabled', true) && config('ai-meter.features.dashboard', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PruneCommand::class]);

            $this->publishes([
                __DIR__ . '/../config/ai-meter.php' => config_path('ai-meter.php'),
            ], 'ai-meter-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/ai-meter'),
            ], 'ai-meter-views');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'ai-meter-migrations');
        }
    }

    protected function makePriceBook(): PriceBook
    {
        $data = [];
        $path = __DIR__ . '/../resources/prices/pricebook.json';

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);

            if (is_array($decoded)) {
                unset($decoded['_comment']);
                $data = $decoded;
            }
        }

        // Config overrides/extends the bundled table.
        $data = array_replace_recursive($data, (array) config('ai-meter.pricing.prices', []));

        $unknown = config('ai-meter.pricing.unknown_model');
        $default = is_array($unknown) ? ModelPrice::fromArray($unknown) : null;

        return PriceBook::fromArray($data, $default);
    }
}
