@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.run_detail'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 6);
    $dateFormat = config('ai-meter.date_format', 'Y-m-d H:i:s');
    $card = 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900';
    $dot = fn ($e) => $e === 'created' ? 'bg-emerald-500' : ($e === 'updated' ? 'bg-amber-500' : ($e === 'deleted' ? 'bg-rose-500' : 'bg-slate-400'));
@endphp

@section('content')
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('ai-meter.runs') }}" class="text-sm text-emerald-600 hover:underline dark:text-emerald-400">← {{ __('ai-meter::messages.runs') }}</a>
        <h1 class="text-2xl font-bold tracking-tight">{{ __('ai-meter::messages.run_detail') }}</h1>
    </div>

    <div class="{{ $card }} mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div><p class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ __('ai-meter::messages.status') }}</p><p class="mt-1 font-semibold">{{ $run->status }}</p></div>
        <div><p class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ __('ai-meter::messages.cost') }}</p><p class="mt-1 font-semibold tabular-nums">{{ $fmt($run->cost_usd) }}{{ $run->budget_usd ? ' / ' . $fmt($run->budget_usd) : '' }}</p></div>
        <div><p class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ __('ai-meter::messages.steps') }}</p><p class="mt-1 font-semibold tabular-nums">{{ $run->step_count }}</p></div>
        <div><p class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ __('ai-meter::messages.tool_calls') }}</p><p class="mt-1 font-semibold tabular-nums">{{ $run->tool_call_count }}{{ $run->max_tool_calls ? ' / ' . $run->max_tool_calls : '' }}</p></div>
    </div>

    @if ($run->error)
        <div class="{{ $card }} mb-6 border-rose-200 dark:border-rose-500/30"><pre class="overflow-auto text-xs text-rose-600 dark:text-rose-400">{{ $run->error }}</pre></div>
    @endif

    <div class="{{ $card }}">
        <h2 class="mb-4 text-sm font-semibold">{{ __('ai-meter::messages.trace') }}</h2>
        <ol class="relative ml-3 border-l border-slate-200 dark:border-slate-700">
            @forelse ($calls as $call)
                <li class="relative py-3 pl-6">
                    <span class="absolute -left-[5px] top-4 h-2.5 w-2.5 rounded-full ring-4 ring-white dark:ring-slate-900 {{ $dot($call->event ?? '') }}"></span>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <a href="{{ route('ai-meter.calls.show', $call->id) }}" class="font-medium text-emerald-700 hover:underline dark:text-emerald-400">{{ $call->model ?? '—' }}</a>
                            @if ($call->tool_name)<span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800">tool: {{ $call->tool_name }}</span>@endif
                            <span class="ml-1 text-xs text-slate-400">{{ $call->provider }}</span>
                        </div>
                        <div class="text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($call->total_tokens) }} tok · {{ $fmt($call->cost_usd) }}{{ $call->latency_ms !== null ? ' · ' . $call->latency_ms . 'ms' : '' }}</div>
                    </div>
                    <div class="mt-0.5 text-xs text-slate-400">{{ optional($call->created_at)->format($dateFormat) }}</div>
                </li>
            @empty
                <li class="py-3 pl-6 text-sm text-slate-400">{{ __('ai-meter::messages.no_calls') }}</li>
            @endforelse
        </ol>
    </div>
@endsection
