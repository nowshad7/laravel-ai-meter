<?php

namespace Nsd7\AiMeter\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Nsd7\AiMeter\Export\CallLogExporter;

/**
 * Export the (optionally filtered) call log as CSV or JSON, to a file or stdout.
 */
class ExportCallsCommand extends Command
{
    protected $signature = 'ai-meter:export
        {--format=csv : Output format: csv or json}
        {--output= : File to write to (defaults to stdout)}
        {--provider=} {--model=} {--status=} {--source=} {--run-id=}
        {--scope-type=} {--scope-id=} {--search=}
        {--since= : Only calls on/after this date/time}
        {--until= : Only calls on/before this date/time}
        {--with-io : Include the prompt/response and properties columns}';

    protected $description = 'Export the AI Meter call log as CSV or JSON.';

    public function handle(CallLogExporter $exporter): int
    {
        $format = $this->option('format') === 'json' ? 'json' : 'csv';
        $withIo = (bool) $this->option('with-io');

        $query = $exporter->query([
            'provider' => $this->option('provider'),
            'model' => $this->option('model'),
            'status' => $this->option('status'),
            'source' => $this->option('source'),
            'run_id' => $this->option('run-id'),
            'scope_type' => $this->option('scope-type'),
            'scope_id' => $this->option('scope-id'),
            'search' => $this->option('search'),
            'since' => $this->option('since'),
            'until' => $this->option('until'),
        ]);

        $count = (clone $query)->count();
        $chunks = $format === 'json' ? $exporter->json($query, $withIo) : $exporter->csv($query, $withIo);

        $output = $this->option('output');

        if ($output) {
            File::ensureDirectoryExists(dirname($output));
            $handle = fopen($output, 'w');

            foreach ($chunks as $chunk) {
                fwrite($handle, $chunk);
            }

            fclose($handle);
            $this->info("Exported {$count} call(s) to {$output} ({$format}).");

            return self::SUCCESS;
        }

        foreach ($chunks as $chunk) {
            $this->output->write($chunk);
        }

        return self::SUCCESS;
    }
}
