@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.runs'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 6);
    $dateFormat = config('ai-meter.date_format', 'Y-m-d H:i:s');
    $badge = function ($s) {
        return match ($s) {
            'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300',
            'running' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/10 dark:text-sky-300',
            'aborted' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
            default => 'bg-rose-100 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300',
        };
    };
@endphp

@section('content')
    <h1 class="mb-6 text-2xl font-bold tracking-tight">{{ __('ai-meter::messages.runs') }}</h1>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900/60">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th class="px-4 py-3">{{ __('ai-meter::messages.run') }}</th>
                        <th class="px-4 py-3">{{ __('ai-meter::messages.scope') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('ai-meter::messages.steps') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('ai-meter::messages.tool_calls') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('ai-meter::messages.cost') }}</th>
                        <th class="px-4 py-3">{{ __('ai-meter::messages.started') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($runs as $run)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $run->label ?? \Illuminate\Support\Str::limit($run->run_id, 12) }}</div>
                                <div class="text-xs"><span class="rounded px-1 {{ $badge($run->status) }}">{{ $run->status }}</span>
                                    @if ($run->budget_usd)<span class="text-slate-400"> · budget {{ $fmt($run->budget_usd) }}</span>@endif</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $run->scope_type ? $run->scope_type . ($run->scope_id !== null ? ':' . $run->scope_id : '') : '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $run->step_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $run->tool_call_count }}{{ $run->max_tool_calls ? ' / ' . $run->max_tool_calls : '' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ $fmt($run->cost_usd) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">{{ optional($run->started_at)->format($dateFormat) }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('ai-meter.runs.show', $run->id) }}" class="rounded px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10">{{ __('ai-meter::messages.details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-16 text-center text-slate-500">{{ __('ai-meter::messages.no_runs') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $runs->links() }}</div>
    </div>
@endsection
