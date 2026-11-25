<!DOCTYPE html>
<html lang="en" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Explore the InfraGuard server-room monitoring dashboard with sample telemetry.">
        <title>InfraGuard | Dashboard Demo</title>
        <link rel="icon" href="./favicon.ico">
        <script>
            if (localStorage.getItem('tailadmin-theme-v1') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/monitoring-widgets.jsx'])
    </head>
    <body class="app-shell min-h-screen overflow-x-hidden" data-pages-preview x-data>
        <header class="border-b border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
            <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-x-5 gap-y-3 px-4 py-4 sm:px-6 lg:px-8">
                <a href="./" class="font-display text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">InfraGuard</a>
                <nav aria-label="Dashboard sections" class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-medium text-slate-600 dark:text-slate-300">
                    <a href="#overview">Overview</a>
                    <a href="#telemetry">Telemetry</a>
                    <a href="#ai-health">AI health</a>
                    <a href="https://github.com/baha-eddine-ammar/infraguard">Source code</a>
                    <button type="button"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 dark:border-white/20"
                        @click="$store.theme.toggle()"
                        :aria-pressed="String($store.theme.theme === 'dark')">
                        Dark mode
                    </button>
                </nav>
            </div>
        </header>
        <main class="app-main-content px-4 pt-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </body>
</html>
