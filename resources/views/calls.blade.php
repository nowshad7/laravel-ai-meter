@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.calls'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 6);
    $dateFormat = config('ai-meter.date_format', 'Y-m-d H:i:s');
    $input = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';
    $badge = fn ($s) => $s === 'success' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300';
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('ai-meter::messages.calls') }}</h1>
        <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-500 dark:text-slate-400">{{ __('ai-meter::messages.export') }}:</span>
            <a href="{{ route('ai-meter.calls.export', array_merge($filters, ['format' => 'csv'])) }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">{{ __('ai-meter::messages.export_csv') }}</a>
            <a href="{{ route('ai-meter.calls.export', array_merge($filters, ['format' => 'json'])) }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">{{ __('ai-meter::messages.export_json') }}</a>
        </div>
    </div>

    <form method="GET" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('ai-meter::messages.search') }}" class="{{ $input }}">
        <select name="provider" class="{{ $input }}"><option value="">{{ __('ai-meter::messages.all_providers') }}</option>@foreach ($providers as $p)<option value="{{ $p }}" @selected(($filters['provider'] ?? '') === $p)>{{ $p }}</option>@endforeach</select>
        <select name="model" class="{{ $input }}"><option value="">{{ __('ai-meter::messages.all_models') }}</option>@foreach ($models as $m)<option value="{{ $m }}" @selected(($filters['model'] ?? '') === $m)>{{ $m }}</option>@endforeach</select>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">{{ __('ai-meter::messages.filter') }}</button>
            <a href="{{ route('ai-meter.calls') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">{{ __('ai-meter::messages.reset') }}</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900/60">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th class="px-4 py-3">{{ __('ai-meter::messages.model') }}</th>
                        <th class="px-4 py-3">{{ __('ai-meter::messages.scope') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('ai-meter::messages.tokens') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('ai-meter::messages.cost') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('ai-meter::messages.latency') }}</th>
                        <th class="px-4 py-3">{{ __('ai-meter::messages.when') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($calls as $call)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $call->model ?? '—' }}</div>
                                <div class="text-xs text-slate-400">{{ $call->provider }} · <span class="rounded px-1 {{ $badge($call->status) }}">{{ $call->status }}</span></div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $call->scope_type ? $call->scope_type . ($call->scope_id !== null ? ':' . $call->scope_id : '') : '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($call->total_tokens) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ $fmt($call->cost_usd) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $call->latency_ms !== null ? $call->latency_ms . 'ms' : '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">{{ optional($call->created_at)->format($dateFormat) }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('ai-meter.calls.show', $call->id) }}" class="rounded px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10">{{ __('ai-meter::messages.details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-16 text-center text-slate-500">{{ __('ai-meter::messages.no_calls') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $calls->links() }}</div>
    </div>
@endsection
