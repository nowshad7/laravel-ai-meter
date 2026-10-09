<?php

namespace Nsd7\AiMeter\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Refresh the price book from a source (a URL or a local JSON file) into an
 * app-owned override file that takes precedence over the bundled, install-
 * frozen prices — so costs can be kept current without a package upgrade.
 */
class UpdatePricesCommand extends Command
{
    protected $signature = 'ai-meter:update-prices
        {--source= : URL or file path to fetch the price book from (defaults to pricing.update.source)}
        {--path= : Destination JSON file to write (defaults to pricing.prices_path)}
        {--replace : Overwrite the destination instead of merging into it}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Update the AI Meter price book from a source so token costs stay current.';

    public function handle(): int
    {
        $source = (string) ($this->option('source') ?: config('ai-meter.pricing.update.source', ''));

        if ($source === '') {
            $this->error('No price source configured. Pass --source=<url|file> or set pricing.update.source in config/ai-meter.php.');

            return self::FAILURE;
        }

        try {
            $raw = $this->fetch($source);
        } catch (Throwable $e) {
            $this->error("Could not read price source [{$source}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        $incoming = $this->normalize($raw);

        if ($incoming === []) {
            $this->error('The price source contained no valid "provider => model => {input, output}" entries.');

            return self::FAILURE;
        }

        $path = $this->destination();
        $existing = $this->option('replace') ? [] : $this->readJson($path);

        [$added, $updated] = $this->diff($existing, $incoming);
        $merged = array_replace_recursive($existing, $incoming);

        $models = array_sum(array_map('count', $incoming));
        $this->info(sprintf(
            'Fetched %d model price(s) across %d provider(s) from %s.',
            $models,
            count($incoming),
            $source,
        ));
        $this->line("  <info>{$added}</info> new, <info>{$updated}</info> changed vs. {$path}");

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing written.');

            return self::SUCCESS;
        }

        $this->write($path, $merged);
        $this->info("Price book written to {$path}.");
        $this->line('  Set <comment>pricing.prices_path</comment> to this path (or AI_METER_PRICES_PATH) so it is loaded.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetch(string $source): array
    {
        if (preg_match('#^https?://#i', $source)) {
            $response = Http::acceptJson()->timeout(20)->get($source);
            $response->throw();
            $decoded = $response->json();
        } else {
            if (! is_file($source)) {
                throw new \RuntimeException('file not found');
            }

            $decoded = json_decode((string) file_get_contents($source), true);
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('expected a JSON object');
        }

        return $decoded;
    }

    /**
     * Reduce arbitrary input to a clean provider => model => price map, keeping
     * only numeric input/output/cached_input. Accepts a `{ "prices": {…} }`
     * wrapper and ignores the `_comment` marker.
     *
     * @param array<string, mixed> $raw
     * @return array<string, array<string, array<string, float>>>
     */
    protected function normalize(array $raw): array
    {
        unset($raw['_comment']);

        if (isset($raw['prices']) && is_array($raw['prices'])) {
            $raw = $raw['prices'];
        }

        $out = [];

        foreach ($raw as $provider => $models) {
            if (! is_string($provider) || ! is_array($models)) {
                continue;
            }

            foreach ($models as $model => $price) {
                if (! is_string($model) || ! is_array($price)) {
                    continue;
                }

                if (! isset($price['input'], $price['output']) || ! is_numeric($price['input']) || ! is_numeric($price['output'])) {
                    continue;
                }

                $entry = [
                    'input' => (float) $price['input'],
                    'output' => (float) $price['output'],
                ];

                if (isset($price['cached_input']) && is_numeric($price['cached_input'])) {
                    $entry['cached_input'] = (float) $price['cached_input'];
                }

                $out[$provider][$model] = $entry;
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<string, array<string, float>>> $existing
     * @param array<string, array<string, array<string, float>>> $incoming
     * @return array{0: int, 1: int} [added, updated]
     */
    protected function diff(array $existing, array $incoming): array
    {
        $added = 0;
        $updated = 0;

        foreach ($incoming as $provider => $models) {
            foreach ($models as $model => $price) {
                $before = $existing[$provider][$model] ?? null;

                if ($before === null) {
                    $added++;
                } elseif ($before !== $price) {
                    $updated++;
                }
            }
        }

        return [$added, $updated];
    }

    protected function destination(): string
    {
        return (string) ($this->option('path')
            ?: config('ai-meter.pricing.prices_path')
            ?: storage_path('app/ai-meter/prices.json'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        unset($decoded['_comment']);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function write(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));

        $payload = array_merge(
            ['_comment' => 'Generated by ai-meter:update-prices. USD per 1,000,000 tokens. Loaded via pricing.prices_path; config pricing.prices still overrides.'],
            $data,
        );

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
