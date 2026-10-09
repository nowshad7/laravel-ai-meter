<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Nsd7\AiMeter\Core\Contracts\PriceProvider;
use Nsd7\AiMeter\Tests\TestCase;

class UpdatePricesTest extends TestCase
{
    protected string $dest;

    protected string $sourceFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dest = storage_path('app/ai-meter/prices-test.json');
        $this->sourceFile = storage_path('app/ai-meter/source.json');
        File::ensureDirectoryExists(dirname($this->dest));
        @unlink($this->dest);
        @unlink($this->sourceFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->dest);
        @unlink($this->sourceFile);

        parent::tearDown();
    }

    protected function writeSource(array $data): string
    {
        File::put($this->sourceFile, json_encode($data));

        return $this->sourceFile;
    }

    protected function readDest(): array
    {
        $decoded = json_decode((string) file_get_contents($this->dest), true);
        unset($decoded['_comment']);

        return $decoded;
    }

    public function test_it_writes_a_price_book_from_a_file_source()
    {
        $source = $this->writeSource([
            '_comment' => 'ignore me',
            'acme' => ['x1' => ['input' => 2, 'output' => 6, 'cached_input' => 1]],
        ]);

        $this->artisan('ai-meter:update-prices', ['--source' => $source, '--path' => $this->dest])
            ->assertSuccessful();

        $this->assertFileExists($this->dest);
        $this->assertEquals(
            ['acme' => ['x1' => ['input' => 2.0, 'output' => 6.0, 'cached_input' => 1.0]]],
            $this->readDest(),
        );
    }

    public function test_the_written_book_is_picked_up_by_the_price_provider()
    {
        config()->set('ai-meter.pricing.prices_path', $this->dest);
        $source = $this->writeSource(['acme' => ['x1' => ['input' => 2, 'output' => 6]]]);

        $this->artisan('ai-meter:update-prices', ['--source' => $source, '--path' => $this->dest])
            ->assertSuccessful();

        $this->app->forgetInstance(PriceProvider::class);
        $price = $this->app->make(PriceProvider::class)->priceFor('acme', 'x1');

        $this->assertNotNull($price);
        $this->assertSame(2.0, $price->inputPerMillion);
        $this->assertSame(6.0, $price->outputPerMillion);
    }

    public function test_it_merges_into_an_existing_destination()
    {
        File::put($this->dest, json_encode(['acme' => ['x1' => ['input' => 1, 'output' => 2]]]));
        $source = $this->writeSource([
            'acme' => ['x2' => ['input' => 3, 'output' => 4]], // new model, same provider
            'other' => ['y1' => ['input' => 5, 'output' => 6]],
        ]);

        $this->artisan('ai-meter:update-prices', ['--source' => $source, '--path' => $this->dest])
            ->expectsOutputToContain('2 new, 0 changed')
            ->assertSuccessful();

        $book = $this->readDest();
        $this->assertArrayHasKey('x1', $book['acme']);
        $this->assertArrayHasKey('x2', $book['acme']);
        $this->assertArrayHasKey('other', $book);
    }

    public function test_replace_overwrites_the_destination()
    {
        File::put($this->dest, json_encode(['acme' => ['x1' => ['input' => 1, 'output' => 2]]]));
        $source = $this->writeSource(['other' => ['y1' => ['input' => 5, 'output' => 6]]]);

        $this->artisan('ai-meter:update-prices', ['--source' => $source, '--path' => $this->dest, '--replace' => true])
            ->assertSuccessful();

        $book = $this->readDest();
        $this->assertArrayNotHasKey('acme', $book);
        $this->assertArrayHasKey('other', $book);
    }

    public function test_dry_run_writes_nothing()
    {
        $source = $this->writeSource(['acme' => ['x1' => ['input' => 2, 'output' => 6]]]);

        $this->artisan('ai-meter:update-prices', ['--source' => $source, '--path' => $this->dest, '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->dest);
    }

    public function test_it_fetches_from_a_url_source()
    {
        Http::fake([
            'prices.test/*' => Http::response(['acme' => ['x1' => ['input' => 7, 'output' => 8]]]),
        ]);

        $this->artisan('ai-meter:update-prices', ['--source' => 'https://prices.test/book.json', '--path' => $this->dest])
            ->assertSuccessful();

        $this->assertEquals(['acme' => ['x1' => ['input' => 7.0, 'output' => 8.0]]], $this->readDest());
    }

    public function test_it_unwraps_a_prices_key_and_skips_invalid_entries()
    {
        $source = $this->writeSource([
            'prices' => [
                'acme' => [
                    'good' => ['input' => 1, 'output' => 2],
                    'no-output' => ['input' => 1],
                    'not-an-array' => 'nope',
                ],
            ],
        ]);

        $this->artisan('ai-meter:update-prices', ['--source' => $source, '--path' => $this->dest])
            ->assertSuccessful();

        $this->assertEquals(['acme' => ['good' => ['input' => 1.0, 'output' => 2.0]]], $this->readDest());
    }

    public function test_it_fails_without_a_source()
    {
        config()->set('ai-meter.pricing.update.source', null);

        $this->artisan('ai-meter:update-prices', ['--path' => $this->dest])
            ->expectsOutputToContain('No price source configured')
            ->assertFailed();
    }

    public function test_it_fails_on_a_missing_file_source()
    {
        $this->artisan('ai-meter:update-prices', ['--source' => $this->sourceFile, '--path' => $this->dest])
            ->expectsOutputToContain('Could not read price source')
            ->assertFailed();
    }

    public function test_it_fails_when_the_source_has_no_valid_entries()
    {
        $source = $this->writeSource(['acme' => ['bad' => ['input' => 'x']]]);

        $this->artisan('ai-meter:update-prices', ['--source' => $source, '--path' => $this->dest])
            ->expectsOutputToContain('no valid')
            ->assertFailed();
    }
}
