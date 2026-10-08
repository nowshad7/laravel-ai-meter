@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.call_detail'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 8);
    $dateFormat = config('ai-meter.date_format', 'Y-m-d H:i:s');
    $card = 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900';
    $rows = [
        'Provider' => $call->provider, 'Model' => $call->model, 'Operation' => $call->operation,
        'Status' => $call->status, 'Source' => $call->source,
        'Scope' => $call->scope_type ? $call->scope_type . ($call->scope_id !== null ? ':' . $call->scope_id : '') : '—',
        'Causer' => $call->causer_type ? $call->causer_type . ':' . $call->causer_id : '—',
        'Run' => $call->run_id ?? '—', 'Trace' => $call->trace_id ?? '—', 'Tool' => $call->tool_name ?? '—',
        'Prompt tokens' => number_format($call->prompt_tokens), 'Completion tokens' => number_format($call->completion_tokens),
        'Total tokens' => number_format($call->total_tokens), 'Cached tokens' => number_format($call->cached_tokens),
        'Cost (USD)' => $fmt($call->cost_usd), 'Latency' => $call->latency_ms !== null ? $call->latency_ms . 'ms' : '—',
        'When' => optional($call->created_at)->format($dateFormat),
    ];
@endphp

@section('content')
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('ai-meter.calls') }}" class="text-sm text-emerald-600 hover:underline dark:text-emerald-400">← {{ __('ai-meter::messages.calls') }}</a>
        <h1 class="text-2xl font-bold tracking-tight">{{ __('ai-meter::messages.call_detail') }} #{{ $call->id }}</h1>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="{{ $card }}">
            <dl class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                @foreach ($rows as $label => $value)
                    <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt><dd class="text-right font-medium">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </div>
        <div class="space-y-6">
            @if ($call->error)
                <div class="{{ $card }} border-rose-200 dark:border-rose-500/30">
                    <h2 class="mb-2 text-sm font-semibold text-rose-600 dark:text-rose-400">{{ __('ai-meter::messages.error') }}</h2>
                    <pre class="overflow-auto text-xs">{{ $call->error }}</pre>
                </div>
            @endif
            @foreach (['input' => 'prompt', 'output' => 'response'] as $field => $key)
                @if ($call->{$field})
                    <div class="{{ $card }}">
                        <h2 class="mb-2 text-sm font-semibold">{{ __('ai-meter::messages.' . $key) }}</h2>
                        <pre class="max-h-72 overflow-auto whitespace-pre-wrap rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-800 dark:bg-slate-950">{{ $call->{$field} }}</pre>
                    </div>
                @endif
            @endforeach
            @if ($call->properties)
                <div class="{{ $card }}">
                    <h2 class="mb-2 text-sm font-semibold">{{ __('ai-meter::messages.properties') }}</h2>
                    <pre class="max-h-72 overflow-auto rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-800 dark:bg-slate-950">{{ json_encode($call->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
        </div>
    </div>
@endsection
