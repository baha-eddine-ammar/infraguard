@php
    $metricsByKey = collect($serverCard['metrics'] ?? [])->keyBy('key');
    $latestMetric = $serverCard['latestMetric'] ?? [];
    $cpuMetric = $metricsByKey->get('cpu', []);
    $ramMetric = $metricsByKey->get('ram', []);
    $diskMetric = $metricsByKey->get('disk', []);
    $networkMetric = $metricsByKey->get('network', []);

    $stateMetrics = [
        'cpu' => [
            'label' => 'CPU usage',
            'detail' => 'Processor load',
            'main' => $cpuMetric['value'] ?? '--',
            'progress' => $cpuMetric['progress'] ?? 0,
            'progressLabel' => $cpuMetric['progressLabel'] ?? 'Pending',
            'accent' => 'from-sky-400 to-cyan-400',
        ],
        'ram' => [
            'label' => 'RAM usage',
            'detail' => $ramMetric['value'] ?? 'Waiting for sample',
            'main' => isset($ramMetric['progress']) ? $ramMetric['progress'].'%' : '--',
            'progress' => $ramMetric['progress'] ?? 0,
            'progressLabel' => $ramMetric['progressLabel'] ?? 'Pending',
            'accent' => 'from-violet-400 to-fuchsia-400',
        ],
        'disk' => [
            'label' => 'Disk usage',
            'detail' => $diskMetric['value'] ?? 'Waiting for sample',
            'main' => isset($diskMetric['progress']) ? $diskMetric['progress'].'%' : '--',
            'progress' => $diskMetric['progress'] ?? 0,
            'progressLabel' => $diskMetric['progressLabel'] ?? 'Pending',
            'accent' => 'from-rose-400 to-pink-400',
        ],
        'network' => [
            'label' => 'Network status',
            'detail' => sprintf(
                'Rx %s Mbps / Tx %s Mbps',
                number_format((float) data_get($latestMetric, 'network.rxMbps', 0), 1),
                number_format((float) data_get($latestMetric, 'network.txMbps', 0), 1),
            ),
            'main' => $networkMetric['value'] ?? 'Connected',
            'progress' => $networkMetric['progress'] ?? 0,
            'progressLabel' => $networkMetric['progressLabel'] ?? 'Pending',
            'accent' => 'from-emerald-400 to-teal-400',
        ],
    ];
@endphp

<article
    class="dashboard-panel relative overflow-hidden px-5 py-5 sm:px-7 sm:py-7"
    data-server-state-card
    data-feed-url="{{ $feedUrl }}"
>
    <script type="application/json" data-server-state-payload>{!! json_encode($serverCard, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT) !!}</script>

    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_0%,_rgba(34,211,238,0.16),_transparent_28%),radial-gradient(circle_at_100%_12%,_rgba(16,185,129,0.14),_transparent_30%)]"></div>

    <div class="relative z-[1]">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/85 text-slate-800 shadow-lg shadow-slate-200/70 backdrop-blur dark:bg-white/10 dark:text-white dark:shadow-none">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <rect x="4" y="4" width="16" height="6" rx="2"></rect>
                        <rect x="4" y="14" width="16" height="6" rx="2"></rect>
                        <path d="M8 7h.01M8 17h.01M12 7h4M12 17h4" stroke-linecap="round"></path>
                    </svg>
                </div>

                <div class="min-w-0">
                    <p class="text-sm font-semibold uppercase text-slate-500 dark:text-slate-400">Live infrastructure state</p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h2 class="break-words font-display text-2xl font-semibold text-slate-950 dark:text-white sm:text-3xl" data-server-name>
                            {{ $serverCard['name'] }}
                        </h2>
                        <span
                            class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold"
                            data-server-status
                        >
                            {{ $serverCard['status'] }}
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300">
                            <span class="dashboard-live-dot"></span>
                            <span data-poll-state>Polling</span>
                        </span>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400" data-server-identifier>
                        {{ $serverCard['identifier'] ?? 'Monitoring node' }}
                    </p>
                </div>
            </div>

            <div class="dashboard-surface-glass rounded-2xl px-4 py-3 text-left lg:text-right">
                <p class="text-xs font-semibold uppercase text-slate-400 dark:text-slate-500">Last seen</p>
                <p class="mt-2 font-display text-xl font-semibold text-slate-950 dark:text-white" data-server-last-seen>
                    {{ $serverCard['lastSeenLabel'] ?? 'Waiting' }}
                </p>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($stateMetrics as $key => $metric)
                <section class="dashboard-surface-glass rounded-2xl px-4 py-4" data-metric-tile="{{ $key }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400" data-metric-label>{{ $metric['label'] }}</p>
                            <p class="mt-1 text-xs font-medium text-slate-400 dark:text-slate-500" data-metric-state>
                                {{ ($metric['progress'] ?? 0) >= 90 ? 'Critical' : (($metric['progress'] ?? 0) >= 75 ? 'High' : 'Normal') }}
                            </p>
                        </div>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_18px_rgba(52,211,153,0.65)]" data-metric-dot></span>
                    </div>

                    <div class="mt-5">
                        <p class="font-display text-3xl font-semibold leading-none text-slate-950 dark:text-white" data-metric-main>
                            {{ $metric['main'] }}
                        </p>
                        <p class="mt-2 min-h-5 text-sm text-slate-500 dark:text-slate-400" data-metric-detail>
                            {{ $metric['detail'] }}
                        </p>
                    </div>

                    <div class="mt-4 metric-progress-track h-2.5 bg-slate-100/80 dark:bg-white/[0.06]">
                        <div
                            class="h-full rounded-full bg-gradient-to-r {{ $metric['accent'] }} transition-all duration-500"
                            data-metric-bar
                            style="width: {{ max(0, min(100, (int) $metric['progress'])) }}%;"
                        ></div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3 text-xs font-semibold uppercase text-slate-400 dark:text-slate-500">
                        <span>{{ $key === 'network' ? 'Traffic' : 'Utilization' }}</span>
                        <span data-metric-progress-label>{{ $metric['progressLabel'] }}</span>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="dashboard-surface-glass mt-5 rounded-2xl px-4 py-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-400 dark:text-slate-500">Operational note</p>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400" data-server-narrative>
                        {{ $serverCard['narrative'] ?? 'Telemetry is waiting for the first sample.' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="app-pill bg-white/80 text-slate-700 dark:bg-white/10 dark:text-slate-300" data-server-sample-mode>
                        PHP/Laravel metrics
                    </span>
                    <span class="app-pill bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                        MySQL latest sample
                    </span>
                </div>
            </div>
        </div>
    </div>
</article>

@once
    <script>
        (() => {
            const mountedAttribute = 'serverStateMounted';
            const pollDelay = 5000;
            const statusBaseClass = 'inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold';
            const statusClasses = {
                Live: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300',
                Warning: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300',
                Critical: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300',
                Offline: 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/[0.05] dark:text-slate-300',
            };

            const dotClasses = {
                normal: 'h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_18px_rgba(52,211,153,0.65)]',
                high: 'h-2.5 w-2.5 rounded-full bg-amber-400 shadow-[0_0_18px_rgba(251,191,36,0.6)]',
                critical: 'h-2.5 w-2.5 rounded-full bg-rose-400 shadow-[0_0_18px_rgba(251,113,133,0.65)]',
                offline: 'h-2.5 w-2.5 rounded-full bg-slate-400 shadow-none',
            };

            const clamp = (value) => Math.max(0, Math.min(100, Number(value) || 0));

            const metricFor = (server, key) => (server.metrics || []).find((metric) => metric.key === key) || {};

            const formatNumber = (value) => {
                const numeric = Number(value || 0);
                return numeric.toFixed(1).replace(/\.0$/, '');
            };

            const loadState = (progress, offline = false) => {
                if (offline) {
                    return { label: 'Offline', classKey: 'offline' };
                }

                if (progress >= 90) {
                    return { label: 'Critical', classKey: 'critical' };
                }

                if (progress >= 75) {
                    return { label: 'High', classKey: 'high' };
                }

                return { label: 'Normal', classKey: 'normal' };
            };

            const text = (root, selector, value) => {
                const element = root.querySelector(selector);

                if (element) {
                    element.textContent = value;
                }
            };

            const updateTile = (root, server, key) => {
                const tile = root.querySelector(`[data-metric-tile="${key}"]`);

                if (!tile) {
                    return;
                }

                const metric = metricFor(server, key);
                const latest = server.latestMetric || {};
                const progress = clamp(metric.progress);
                const isNetwork = key === 'network';
                const network = latest.network || {};
                const networkOffline = isNetwork && network.connected === false;
                const state = loadState(progress, networkOffline);
                const mainValue = key === 'cpu'
                    ? (metric.value || '--')
                    : isNetwork
                        ? (networkOffline ? 'Disconnected' : (metric.value || 'Connected'))
                        : `${Math.round(progress)}%`;
                const detail = key === 'ram' || key === 'disk'
                    ? (metric.value || 'Waiting for sample')
                    : isNetwork
                        ? `Rx ${formatNumber(network.rxMbps)} Mbps / Tx ${formatNumber(network.txMbps)} Mbps`
                        : 'Processor load';

                text(tile, '[data-metric-main]', mainValue);
                text(tile, '[data-metric-detail]', detail);
                text(tile, '[data-metric-state]', state.label);
                text(tile, '[data-metric-progress-label]', metric.progressLabel || `${Math.round(progress)}%`);

                const bar = tile.querySelector('[data-metric-bar]');
                const dot = tile.querySelector('[data-metric-dot]');

                if (bar) {
                    bar.style.width = `${progress}%`;
                }

                if (dot) {
                    dot.className = dotClasses[state.classKey] || dotClasses.normal;
                }
            };

            const applyServer = (root, server) => {
                if (!server) {
                    return;
                }

                text(root, '[data-server-name]', server.name || 'Monitoring node');
                text(root, '[data-server-identifier]', server.identifier || 'Monitoring node');
                text(root, '[data-server-last-seen]', server.lastSeenLabel || 'Waiting');
                text(root, '[data-server-narrative]', server.narrative || 'Telemetry is waiting for the first sample.');

                const status = root.querySelector('[data-server-status]');

                if (status) {
                    status.textContent = server.status || 'Offline';
                    status.className = `${statusBaseClass} ${statusClasses[server.status] || statusClasses.Offline}`;
                }

                ['cpu', 'ram', 'disk', 'network'].forEach((key) => updateTile(root, server, key));
            };

            const mount = (root) => {
                if (root.dataset[mountedAttribute] === 'true') {
                    return;
                }

                root.dataset[mountedAttribute] = 'true';

                const feedUrl = root.dataset.feedUrl;
                const state = root.querySelector('[data-poll-state]');
                const payload = root.querySelector('[data-server-state-payload]');
                let inFlight = false;

                if (payload?.textContent) {
                    try {
                        applyServer(root, JSON.parse(payload.textContent));
                    } catch (error) {
                        console.warn('Unable to parse initial server state payload.', error);
                    }
                }

                const buildPollUrl = () => {
                    const url = new URL(feedUrl, window.location.href);
                    url.searchParams.set('t', Date.now().toString());

                    return url.toString();
                };

                const poll = async () => {
                    if (!feedUrl || inFlight) {
                        return;
                    }

                    inFlight = true;

                    if (state) {
                        state.textContent = 'Refreshing';
                    }

                    try {
                        const response = await fetch(buildPollUrl(), {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            cache: 'no-store',
                        });

                        if (!response.ok) {
                            throw new Error(`Server state request failed with ${response.status}`);
                        }

                        const payload = await response.json();
                        applyServer(root, payload.server || payload);

                        if (state) {
                            state.textContent = 'Polling';
                        }
                    } catch (error) {
                        if (state) {
                            state.textContent = 'Stale';
                        }

                        console.warn('Server state polling failed.', error);
                    } finally {
                        inFlight = false;
                    }
                };

                window.setInterval(() => {
                    void poll();
                }, pollDelay);

                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        void poll();
                    }
                });

                void poll();
            };

            const mountAll = () => {
                document.querySelectorAll('[data-server-state-card]').forEach(mount);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', mountAll, { once: true });
            } else {
                mountAll();
            }
        })();
    </script>
@endonce
