<?php

namespace Nsd7\AiMeter\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Nsd7\AiMeter\AiMeterServiceProvider;
use Nsd7\AiMeter\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [AiMeterServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        (require __DIR__ . '/../database/migrations/2024_01_01_000000_create_ai_meter_calls_table.php')->up();
        (require __DIR__ . '/../database/migrations/2024_01_01_000001_create_ai_meter_runs_table.php')->up();
    }

    protected function defineRoutes($router)
    {
        Route::get('/login', fn () => 'login')->name('login');
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::create(array_merge(['name' => 'Jane', 'email' => 'jane@example.com'], $attributes));
    }

    /**
     * A duck-typed stand-in for a Prism response.
     */
    protected function fakePrismResponse(int $prompt, int $completion, string $model = 'gpt-4o'): object
    {
        return (object) [
            'usage' => (object) [
                'promptTokens' => $prompt,
                'completionTokens' => $completion,
                'totalTokens' => $prompt + $completion,
            ],
            'meta' => (object) ['model' => $model],
        ];
    }
}
