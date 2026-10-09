@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.calls'))
@section('page_title', __('ai-meter::messages.calls'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 6);
    $dateFormat = config('ai-meter.date_format', 'Y-m-d H:i:s');
    $badge = fn ($s) => $s === 'success'
        ? 'bg-brand-100 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300'
        : 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300';
@endphp

@section('actions')
    <a href="{{ route('ai-meter.calls.export', array_merge($filters, ['format' => 'csv'])) }}" class="alm-btn-ghost">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h5l2 2h7a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
        {{ __('ai-meter::messages.export_csv') }}
    </a>
    <a href="{{ route('ai-meter.calls.export', array_merge($filters, ['format' => 'json'])) }}" class="alm-btn-ghost">{{ __('ai-meter::messages.export_json') }}</a>
@endsection

@section('content')
    <form method="GET" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('ai-meter::messages.search') }}" class="alm-input">
        <select name="provider" class="alm-input"><option value="">{{ __('ai-meter::messages.all_providers') }}</option>@foreach ($providers as $p)<option value="{{ $p }}" @selected(($filters['provider'] ?? '') === $p)>{{ $p }}</option>@endforeach</select>
        <select name="model" class="alm-input"><option value="">{{ __('ai-meter::messages.all_models') }}</option>@foreach ($models as $m)<option value="{{ $m }}" @selected(($filters['model'] ?? '') === $m)>{{ $m }}</option>@endforeach</select>
        <div class="flex gap-2">
            <button type="submit" class="alm-btn flex-1">{{ __('ai-meter::messages.filter') }}</button>
            <a href="{{ route('ai-meter.calls') }}" class="alm-btn-ghost">{{ __('ai-meter::messages.reset') }}</a>
        </div>
    </form>

    <div class="alm-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50/80 dark:bg-slate-900/60">
                    <tr>
                        <th class="alm-th">{{ __('ai-meter::messages.model') }}</th>
                        <th class="alm-th">{{ __('ai-meter::messages.scope') }}</th>
                        <th class="alm-th text-right">{{ __('ai-meter::messages.tokens') }}</th>
                        <th class="alm-th text-right">{{ __('ai-meter::messages.cost') }}</th>
                        <th class="alm-th text-right">{{ __('ai-meter::messages.latency') }}</th>
                        <th class="alm-th">{{ __('ai-meter::messages.when') }}</th>
                        <th class="alm-th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse ($calls as $call)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="alm-td">
                                <div class="font-semibold">{{ $call->model ?? '—' }}</div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-400">
                                    {{ $call->provider }}
                                    <span class="alm-badge {{ $badge($call->status) }}">{{ $call->status }}</span>
                                </div>
                            </td>
                            <td class="alm-td font-mono text-xs text-slate-500 dark:text-slate-400">{{ $call->scope_type ? $call->scope_type . ($call->scope_id !== null ? ':' . $call->scope_id : '') : '—' }}</td>
                            <td class="alm-td text-right tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($call->total_tokens) }}</td>
                            <td class="alm-td text-right tabular-nums font-semibold">{{ $fmt($call->cost_usd) }}</td>
                            <td class="alm-td text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $call->latency_ms !== null ? $call->latency_ms . 'ms' : '—' }}</td>
                            <td class="alm-td whitespace-nowrap text-slate-500 dark:text-slate-400">{{ optional($call->created_at)->format($dateFormat) }}</td>
                            <td class="alm-td text-right"><a href="{{ route('ai-meter.calls.show', $call->id) }}" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-brand-600 transition hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10">{{ __('ai-meter::messages.details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-20 text-center text-sm text-slate-400">{{ __('ai-meter::messages.no_calls') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-3 dark:border-slate-800">{{ $calls->links() }}</div>
    </div>
@endsection
