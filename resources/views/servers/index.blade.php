<x-app-layout>
    <section class="mx-auto max-w-7xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="app-section-title">Server section</p>
                <h1 class="mt-2 font-display text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    Local PC monitoring
                </h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    CPU, RAM, disk, and network update automatically when your PC collector posts metrics to Laravel.
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="app-status-success mb-6">
                {{ session('success') }}
            </div>
        @endif

        @include('servers.partials.live-state-card', [
            'serverCard' => $serverCard,
            'feedUrl' => $serverStateFeedUrl,
        ])

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Default monitored machine: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $serverCard['name'] }}</span>.
            </p>
            <a href="{{ route('servers.show', $serverCard['id']) }}" class="app-button-secondary">
                View Details
            </a>
        </div>

        @if (auth()->user()?->isDepartmentHead())
            <section class="app-card mt-6 px-6 py-6 sm:px-7">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="app-section-title">Collector token</p>
                        <h2 class="mt-2 font-display text-2xl font-semibold text-gray-900 dark:text-white">
                            Windows PC metrics token
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                            Set the token used by the Windows collector. Paste the same value into
                            <span class="font-mono">tools/pc-metrics-collector/pc-metrics-token.local.txt</span>
                            on your PC.
                        </p>
                    </div>

                    <div class="app-surface-muted min-w-[14rem] px-4 py-4">
                        <p class="text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">Current token</p>
                        @if ($pcMetricsToken['configured'])
                            <p class="mt-2 font-mono text-sm text-gray-900 dark:text-white">
                                {{ $pcMetricsToken['masked'] }}
                            </p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Source: {{ $pcMetricsToken['source'] }}
                                @if ($pcMetricsToken['updatedLabel'])
                                    - Updated {{ $pcMetricsToken['updatedLabel'] }}
                                @endif
                            </p>
                        @else
                            <p class="mt-2 text-sm font-semibold text-amber-600 dark:text-amber-300">
                                Not configured
                            </p>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('servers.pc-metrics-token.update') }}" class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                    @csrf

                    <div>
                        <label for="pc_metrics_token" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            New token
                        </label>
                        <input
                            id="pc_metrics_token"
                            name="pc_metrics_token"
                            type="password"
                            autocomplete="off"
                            class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-white/10 dark:bg-white/[0.03] dark:text-white"
                            placeholder="Paste a secure token, minimum 16 characters"
                        >
                        @error('pc_metrics_token')
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="app-button-primary lg:mt-7">
                        Save Token
                    </button>
                </form>
            </section>
        @endif
    </section>
</x-app-layout>
