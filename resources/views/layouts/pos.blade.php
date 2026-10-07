<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \App\Models\Setting::get('site_title') ?: \App\Models\Setting::get('shop_name', 'Ajmiriganj IT') }} - Point of Sale</title>
    @php
        $faviconPath = \App\Models\Setting::get('favicon');
        $faviconUrl = $faviconPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPath) : asset('favicon.ico');
    @endphp
    <link rel="icon" href="{{ $faviconUrl }}">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full overflow-hidden flex flex-col font-sans select-none bg-[#090d16]">

    <!-- Global Offline Connection Status Banner -->
    <div x-data="{ isOffline: !navigator.onLine }"
         x-init="
             window.addEventListener('online', () => isOffline = false);
             window.addEventListener('offline', () => isOffline = true);
         "
         x-show="isOffline"
         x-cloak
         class="bg-amber-500 text-slate-950 font-bold text-xs px-4 py-2 text-center flex items-center justify-center gap-2 z-50 shadow-md">
        <svg class="w-4 h-4 shrink-0 text-slate-950" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L12 12m-6.364 6.364a9 9 0 010-12.728m0 0l2.829 2.829m-2.829-2.829L12 12"></path></svg>
        <span>Connection lost. You are currently offline. Local actions are preserved; reconnection is required to complete sales.</span>
    </div>

    <!-- POS Header -->
    <header class="h-14 bg-[#111827] border-b border-slate-800 flex items-center justify-between px-3 sm:px-4 shrink-0 shadow-md">
        <div class="flex items-center gap-2 sm:gap-3 overflow-hidden">
            <div class="h-8 w-8 sm:h-9 sm:w-9 rounded-lg bg-brand-500/20 border border-brand-400/40 flex items-center justify-center text-brand-300 font-extrabold text-base sm:text-lg shadow-inner shrink-0">
                ৳
            </div>
            <div class="truncate">
                <h1 class="text-xs sm:text-sm font-bold text-white tracking-wide leading-tight truncate max-w-[130px] sm:max-w-[220px] md:max-w-none">
                    {{ \App\Models\Setting::get('shop_name', 'Ajmiriganj IT') }}
                </h1>
                <span class="text-[10px] sm:text-[11px] text-brand-300 font-medium block leading-none">POS Terminal</span>
            </div>
        </div>

        <!-- Center: Quick Keyboard Help -->
        <div class="hidden lg:flex items-center gap-2 text-[11px] text-slate-400 font-mono">
            <span class="px-1.5 py-0.5 bg-slate-800 rounded border border-slate-700 text-slate-300">F2</span> Search
            <span class="px-1.5 py-0.5 bg-slate-800 rounded border border-slate-700 text-slate-300">F4</span> Hold
            <span class="px-1.5 py-0.5 bg-slate-800 rounded border border-slate-700 text-slate-300">F6</span> Customer
            <span class="px-1.5 py-0.5 bg-slate-800 rounded border border-slate-700 text-slate-300">F8</span> Pay
            <span class="px-1.5 py-0.5 bg-slate-800 rounded border border-slate-700 text-slate-300">Esc</span> Clear
        </div>

        <!-- Right: Cashier, Held Carts, Link to Admin -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <div class="flex items-center gap-1.5 sm:gap-2 px-2 sm:px-2.5 py-1 bg-slate-800/80 rounded-md border border-slate-700/60 text-xs">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
                <span class="text-slate-300 font-medium truncate max-w-[80px] sm:max-w-[140px]">{{ auth()->user()?->name ?? 'Cashier' }}</span>
                <span class="hidden sm:inline text-[10px] text-brand-300 font-semibold px-1.5 py-0.2 bg-brand-900/40 rounded uppercase">
                    {{ auth()->user()?->roles->first()?->name ?? 'User' }}
                </span>
            </div>

            <a href="/admin" class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 transition" title="Back to Admin Dashboard">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span class="hidden sm:inline">Dashboard</span>
            </a>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-hidden relative">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
