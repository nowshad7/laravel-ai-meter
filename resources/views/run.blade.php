@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.run_detail'))
@section('page_title', __('ai-meter::messages.run_detail'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 6);
    $dateFormat = config('ai-meter.date_format', 'Y-m-d H:i:s');
    $dot = fn ($e) => $e === 'created' ? 'bg-brand-500' : ($e === 'updated' ? 'bg-amber-500' : ($e === 'deleted' ? 'bg-rose-500' : 'bg-slate-400'));
    $stats = [
        __('ai-meter::messages.status') => $run->status,
        __('ai-meter::messages.cost') => $fmt($run->cost_usd) . ($run->budget_usd ? ' / ' . $fmt($run->budget_usd) : ''),
        __('ai-meter::messages.steps') => $run->step_count,
        __('ai-meter::messages.tool_calls') => $run->tool_call_count . ($run->max_tool_calls ? ' / ' . $run->max_tool_calls : ''),
    ];
@endphp

@section('actions')
    <a href="{{ route('ai-meter.runs') }}" class="alm-btn-ghost">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        {{ __('ai-meter::messages.runs') }}
    </a>
@endsection

@section('content')
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach ($stats as $label => $value)
            <div class="alm-card-p">
                <p class="alm-label">{{ $label }}</p>
                <p class="mt-2 text-lg font-bold tabular-nums tracking-tight">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if ($run->error)
        <div class="alm-card-p mb-6 border-rose-200 dark:border-rose-500/30"><pre class="overflow-auto text-xs text-rose-600 dark:text-rose-400">{{ $run->error }}</pre></div>
    @endif

    <div class="alm-card-p">
        <h2 class="mb-5 text-sm font-semibold">{{ __('ai-meter::messages.trace') }}</h2>
        <ol class="relative ml-3 border-l border-slate-200 dark:border-slate-700">
            @forelse ($calls as $call)
                <li class="relative py-3 pl-6">
                    <span class="absolute -left-[5px] top-4 h-2.5 w-2.5 rounded-full ring-4 ring-white dark:ring-slate-900 {{ $dot($call->event ?? '') }}"></span>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a href="{{ route('ai-meter.calls.show', $call->id) }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-400">{{ $call->model ?? '—' }}</a>
                            @if ($call->tool_name)<span class="alm-badge bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">tool: {{ $call->tool_name }}</span>@endif
                            <span class="text-xs text-slate-400">{{ $call->provider }}</span>
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
