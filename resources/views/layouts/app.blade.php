<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'AI Meter')</title>
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('ai-meter-theme'); } catch (e) {}
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<div class="min-h-full">
    <nav class="sticky top-0 z-20 border-b border-slate-200 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="{{ route('ai-meter.overview') }}" class="flex items-center gap-2 text-lg font-semibold">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13a9 9 0 1118 0M12 13l4-3"/></svg>
                </span>
                <span>AI Meter</span>
            </a>
            <div class="flex items-center gap-1">
                @php($tabs = ['overview' => 'Overview', 'calls' => 'Calls'])
                @foreach ($tabs as $route => $label)
                    <a href="{{ route('ai-meter.' . $route) }}"
                       @class([
                           'rounded-lg px-3 py-1.5 text-sm font-medium',
                           'bg-emerald-600 text-white' => request()->routeIs('ai-meter.' . $route) || ($route === 'calls' && request()->routeIs('ai-meter.calls.show')),
                           'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => ! (request()->routeIs('ai-meter.' . $route) || ($route === 'calls' && request()->routeIs('ai-meter.calls.show'))),
                       ])>{{ $label }}</a>
                @endforeach
                <button type="button" x-data
                        @click="const d=document.documentElement.classList.toggle('dark');try{localStorage.setItem('ai-meter-theme',d?'dark':'light')}catch(e){}"
                        class="ml-2 rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Toggle dark mode">
                    <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 118.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>
            </div>
        </div>
    </nav>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">@yield('content')</main>
</div>
</body>
</html>
