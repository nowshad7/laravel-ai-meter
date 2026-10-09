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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7',
                            400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857',
                            800: '#065f46', 900: '#064e3b', 950: '#022c22',
                        },
                    },
                },
            },
        };
    </script>
    <style type="text/tailwindcss">
        @layer base {
            body { @apply font-sans antialiased; }
        }
        @layer components {
            .alm-card { @apply rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-black/[0.02] dark:border-slate-800 dark:bg-slate-900 dark:ring-white/[0.02]; }
            .alm-card-p { @apply alm-card p-5 sm:p-6; }
            .alm-label { @apply text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400; }
            .alm-nav { @apply flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white; }
            .alm-nav-active { @apply bg-brand-50 text-brand-700 hover:bg-brand-50 hover:text-brand-700 dark:bg-brand-500/10 dark:text-brand-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-300; }
            .alm-nav svg { @apply h-[18px] w-[18px] shrink-0; }
            .alm-input { @apply block w-full rounded-xl border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100; }
            .alm-btn { @apply inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/40; }
            .alm-btn-ghost { @apply inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800; }
            .alm-badge { @apply inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-xs font-semibold; }
            .alm-th { @apply px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400; }
            .alm-td { @apply px-5 py-3.5 align-middle; }
        }
    </style>
    <style>[x-cloak]{display:none!important}</style>
    @php
        $navSections = [
            __('ai-meter::messages.monitor') => [
                'overview' => ['label' => __('ai-meter::messages.overview'), 'icon' => 'M3 13a9 9 0 1118 0M12 13l4-3'],
                'calls'    => ['label' => __('ai-meter::messages.calls'), 'icon' => 'M8 10h.01M12 10h.01M16 10h.01M21 12a8.96 8.96 0 01-1.3 4.68L21 21l-4.5-1.2A9 9 0 1121 12z'],
            ],
            __('ai-meter::messages.govern') => [
                'budgets' => ['label' => __('ai-meter::messages.budgets'), 'icon' => 'M3 10h18M7 15h1m4 0h5M3 6a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6z'],
            ],
        ];
        if (config('ai-meter.features.runs', true)) {
            $navSections[__('ai-meter::messages.monitor')]['runs'] = ['label' => __('ai-meter::messages.runs'), 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'];
        }
        $isActive = fn ($r) => request()->routeIs('ai-meter.' . $r) || request()->routeIs('ai-meter.' . $r . '.show');
    @endphp
</head>
<body class="h-full bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<div x-data="{ open: false }" class="min-h-full lg:flex">

    {{-- Mobile overlay --}}
    <div x-cloak x-show="open" @click="open = false" class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-sm lg:hidden" x-transition.opacity></div>

    {{-- Sidebar --}}
    <aside x-cloak
           :class="open ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white px-4 py-5 transition-transform duration-200 dark:border-slate-800 dark:bg-slate-900 lg:static lg:translate-x-0">
        <a href="{{ route('ai-meter.overview') }}" class="flex items-center gap-3 px-2">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-md shadow-brand-600/20">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13a9 9 0 1118 0M12 13l4-3"/></svg>
            </span>
            <span class="leading-tight">
                <span class="block text-base font-bold tracking-tight">AI Meter</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('ai-meter::messages.tagline') }}</span>
            </span>
        </a>

        <nav class="mt-7 flex-1 space-y-6 overflow-y-auto">
            @foreach ($navSections as $section => $items)
                <div>
                    <p class="px-3 pb-2 alm-label">{{ $section }}</p>
                    <div class="space-y-1">
                        @foreach ($items as $route => $item)
                            <a href="{{ route('ai-meter.' . $route) }}" @class(['alm-nav', 'alm-nav-active' => $isActive($route)])>
                                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="mt-4 flex items-center justify-between border-t border-slate-200 px-2 pt-4 dark:border-slate-800">
            <a href="https://github.com/nowshad7/laravel-ai-meter" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 transition hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.58 2 12.25c0 4.53 2.87 8.37 6.84 9.73.5.1.68-.22.68-.49 0-.24-.01-.88-.01-1.73-2.78.62-3.37-1.37-3.37-1.37-.46-1.19-1.11-1.5-1.11-1.5-.91-.64.07-.62.07-.62 1 .07 1.53 1.06 1.53 1.06.89 1.57 2.34 1.12 2.91.85.09-.66.35-1.12.63-1.38-2.22-.26-4.55-1.14-4.55-5.07 0-1.12.39-2.03 1.03-2.75-.1-.26-.45-1.3.1-2.71 0 0 .84-.28 2.75 1.05A9.3 9.3 0 0112 6.84c.85 0 1.7.12 2.5.34 1.91-1.33 2.75-1.05 2.75-1.05.55 1.41.2 2.45.1 2.71.64.72 1.03 1.63 1.03 2.75 0 3.94-2.34 4.81-4.57 5.06.36.32.68.94.68 1.9 0 1.37-.01 2.47-.01 2.81 0 .27.18.6.69.49A10.26 10.26 0 0022 12.25C22 6.58 17.52 2 12 2z"/></svg>
                GitHub
            </a>
            <button type="button" x-data
                    @click="const d=document.documentElement.classList.toggle('dark');try{localStorage.setItem('ai-meter-theme',d?'dark':'light')}catch(e){}"
                    class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Toggle dark mode">
                <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 118.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </button>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-slate-100/80 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/80 sm:px-6 lg:px-8">
            <button type="button" @click="open = true" class="rounded-lg p-2 text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-800 lg:hidden" aria-label="Open menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="truncate text-lg font-bold tracking-tight">@yield('page_title', 'AI Meter')</h1>
            <div class="ml-auto flex items-center gap-2">@yield('actions')</div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">@yield('content')</main>
    </div>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
</body>
</html>
