@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.runs'))
@section('page_title', __('ai-meter::messages.runs'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 6);
    $dateFormat = config('ai-meter.date_format', 'Y-m-d H:i:s');
    $badge = function ($s) {
        return match ($s) {
            'completed' => 'bg-brand-100 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300',
            'running' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
            'aborted' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
            default => 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
        };
    };
@endphp

@section('content')
    <div class="alm-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50/80 dark:bg-slate-900/60">
                    <tr>
                        <th class="alm-th">{{ __('ai-meter::messages.run') }}</th>
                        <th class="alm-th">{{ __('ai-meter::messages.scope') }}</th>
                        <th class="alm-th text-right">{{ __('ai-meter::messages.steps') }}</th>
                        <th class="alm-th text-right">{{ __('ai-meter::messages.tool_calls') }}</th>
                        <th class="alm-th text-right">{{ __('ai-meter::messages.cost') }}</th>
                        <th class="alm-th">{{ __('ai-meter::messages.started') }}</th>
                        <th class="alm-th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse ($runs as $run)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="alm-td">
                                <div class="font-semibold">{{ $run->label ?? \Illuminate\Support\Str::limit($run->run_id, 12) }}</div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-xs">
                                    <span class="alm-badge {{ $badge($run->status) }}">{{ $run->status }}</span>
                                    @if ($run->budget_usd)<span class="text-slate-400">budget {{ $fmt($run->budget_usd) }}</span>@endif
                                </div>
                            </td>
                            <td class="alm-td font-mono text-xs text-slate-500 dark:text-slate-400">{{ $run->scope_type ? $run->scope_type . ($run->scope_id !== null ? ':' . $run->scope_id : '') : '—' }}</td>
                            <td class="alm-td text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $run->step_count }}</td>
                            <td class="alm-td text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $run->tool_call_count }}{{ $run->max_tool_calls ? ' / ' . $run->max_tool_calls : '' }}</td>
                            <td class="alm-td text-right tabular-nums font-semibold">{{ $fmt($run->cost_usd) }}</td>
                            <td class="alm-td whitespace-nowrap text-slate-500 dark:text-slate-400">{{ optional($run->started_at)->format($dateFormat) }}</td>
                            <td class="alm-td text-right"><a href="{{ route('ai-meter.runs.show', $run->id) }}" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-brand-600 transition hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10">{{ __('ai-meter::messages.details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-20 text-center text-sm text-slate-400">{{ __('ai-meter::messages.no_runs') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-3 dark:border-slate-800">{{ $runs->links() }}</div>
    </div>
@endsection
