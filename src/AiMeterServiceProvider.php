<?php

namespace Nsd7\AiMeter;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Nsd7\AiMeter\Adapters\LaravelAiListener;
use Nsd7\AiMeter\Budget\BudgetAlerter;
use Nsd7\AiMeter\Budget\CacheSpendStore;
use Nsd7\AiMeter\Budget\DatabaseSpendStore;
use Nsd7\AiMeter\Console\ExportCallsCommand;
use Nsd7\AiMeter\Console\PruneCommand;
use Nsd7\AiMeter\Console\UpdatePricesCommand;
use Nsd7\AiMeter\Core\Contracts\PriceProvider;
use Nsd7\AiMeter\Core\Contracts\Recorder;
use Nsd7\AiMeter\Core\Contracts\Redactor;
use Nsd7\AiMeter\Core\Contracts\SpendStore;
use Nsd7\AiMeter\Core\Pricing\ModelPrice;
use Nsd7\AiMeter\Core\Pricing\PriceBook;
use Nsd7\AiMeter\Events\BudgetThresholdReached;
use Nsd7\AiMeter\Http\Middleware\EnforceBudget;
use Nsd7\AiMeter\Listeners\SendBudgetAlertNotification;
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
        $this->app->singleton(SpendStore::class, function ($app) {
            if (config('ai-meter.spend_store.driver', 'database') === 'cache') {
                return new CacheSpendStore($app['cache']);
            }

            return new DatabaseSpendStore();
        });

        $this->app->singleton(Meter::class, fn ($app) => new Meter(
            $app->make(PriceProvider::class),
            $app->make(Recorder::class),
            $app->make(SpendStore::class),
            new BudgetAlerter($app->make(SpendStore::class), $app['events'], $app['cache']),
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

        if (config('ai-meter.enabled', true)
            && (config('ai-meter.features.dashboard', true) || config('ai-meter.features.api', false))) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        $this->registerAdapters();

        if (config('ai-meter.alerts.enabled', true)) {
            $this->app['events']->listen(BudgetThresholdReached::class, [SendBudgetAlertNotification::class, 'handle']);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PruneCommand::class, UpdatePricesCommand::class, ExportCallsCommand::class]);

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

    /**
     * Wire optional auto-instrumentation adapters when enabled and available.
     */
    protected function registerAdapters(): void
    {
        if (! config('ai-meter.enabled', true)) {
            return;
        }

        $laravelAi = (array) config('ai-meter.adapters.laravel_ai', []);
        $event = $laravelAi['event'] ?? null;

        if (($laravelAi['enabled'] ?? false) && is_string($event) && class_exists($event)) {
            $this->app['events']->listen($event, [LaravelAiListener::class, 'handle']);
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

        // An app-owned override file (e.g. refreshed by ai-meter:update-prices)
        // sits above the bundled, install-frozen table.
        $localPath = config('ai-meter.pricing.prices_path');

        if (is_string($localPath) && is_file($localPath)) {
            $decoded = json_decode((string) file_get_contents($localPath), true);

            if (is_array($decoded)) {
                unset($decoded['_comment']);
                $data = array_replace_recursive($data, $decoded);
            }
        }

        // Config overrides/extends everything above.
        $data = array_replace_recursive($data, (array) config('ai-meter.pricing.prices', []));

        $unknown = config('ai-meter.pricing.unknown_model');
        $default = is_array($unknown) ? ModelPrice::fromArray($unknown) : null;

        return PriceBook::fromArray($data, $default);
    }
}
