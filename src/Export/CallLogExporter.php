<?php

namespace Nsd7\AiMeter\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Nsd7\AiMeter\Models\AiMeterCall;
use Throwable;

/**
 * Builds the filtered call-log query and streams it as CSV or JSON. Shared by
 * the dashboard download and the ai-meter:export command so filtering and
 * column layout stay identical across both.
 */
class CallLogExporter
{
    /** Metadata columns, always exported. */
    public const COLUMNS = [
        'id', 'created_at', 'source', 'provider', 'model', 'operation', 'status',
        'prompt_tokens', 'completion_tokens', 'total_tokens', 'cached_tokens', 'reasoning_tokens',
        'cost_usd', 'currency', 'cost_display', 'latency_ms',
        'trace_id', 'run_id', 'tool_name',
        'scope_type', 'scope_id', 'causer_type', 'causer_id', 'error',
    ];

    /** Prompt/response columns, exported only with $withIo. */
    public const IO_COLUMNS = ['input', 'output', 'properties'];

    /**
     * Apply the call-log filters (the same set the dashboard uses, plus
     * since/until date bounds) and return the ordered query.
     *
     * @param array<string, mixed> $filters
     */
    public function query(array $filters): Builder
    {
        return AiMeterCall::query()
            ->when($filters['provider'] ?? null, fn ($q, $v) => $q->where('provider', $v))
            ->when($filters['model'] ?? null, fn ($q, $v) => $q->where('model', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['run_id'] ?? null, fn ($q, $v) => $q->where('run_id', $v))
            ->when($filters['scope_type'] ?? null, fn ($q, $v) => $q->where('scope_type', $v))
            ->when(($filters['scope_id'] ?? null) !== null && ($filters['scope_id'] ?? '') !== '',
                fn ($q) => $q->where('scope_id', (string) $filters['scope_id']))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('model', 'like', "%{$v}%")->orWhere('provider', 'like', "%{$v}%");
            }))
            ->when($this->date($filters['since'] ?? null), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($this->date($filters['until'] ?? null), fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->orderByDesc('id');
    }

    /**
     * @return array<int, string>
     */
    public function columns(bool $withIo): array
    {
        return $withIo ? array_merge(self::COLUMNS, self::IO_COLUMNS) : self::COLUMNS;
    }

    /**
     * Stream the query as CSV lines (header first). Rows are pulled lazily so
     * large exports stay within memory.
     *
     * @return \Generator<int, string>
     */
    public function csv(Builder $query, bool $withIo): \Generator
    {
        $columns = $this->columns($withIo);

        yield $this->csvLine($columns);

        foreach ($query->lazy() as $call) {
            yield $this->csvLine(array_values($this->toArray($call, $columns, true)));
        }
    }

    /**
     * Stream the query as a JSON array, one object per call.
     *
     * @return \Generator<int, string>
     */
    public function json(Builder $query, bool $withIo): \Generator
    {
        $columns = $this->columns($withIo);

        yield '[';
        $first = true;

        foreach ($query->lazy() as $call) {
            yield ($first ? '' : ',')
                . json_encode($this->toArray($call, $columns, false), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $first = false;
        }

        yield ']';
    }

    /**
     * @param array<int, string> $columns
     * @return array<string, mixed>
     */
    public function toArray(AiMeterCall $call, array $columns, bool $forCsv): array
    {
        $row = [];

        foreach ($columns as $column) {
            $value = match ($column) {
                'created_at' => $call->created_at?->toIso8601String(),
                'properties' => $forCsv
                    ? ($call->properties !== null ? json_encode($call->properties, JSON_UNESCAPED_SLASHES) : null)
                    : $call->properties,
                default => $call->getAttribute($column),
            };

            if ($forCsv && $value === null) {
                $value = '';
            }

            $row[$column] = $value;
        }

        return $row;
    }

    /**
     * @param array<int, mixed> $fields
     */
    protected function csvLine(array $fields): string
    {
        $handle = fopen('php://temp', 'r+');
        // Escape passed explicitly: its default is deprecated on PHP 8.4+.
        fputcsv($handle, array_map(fn ($v) => is_scalar($v) ? $v : (string) $v, $fields), ',', '"', '\\');
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return $line;
    }

    protected function date(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
