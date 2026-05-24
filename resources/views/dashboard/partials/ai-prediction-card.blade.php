@php
    $riskLevel = $latestAiPrediction?->risk_level ?? 'normal';
    $riskLabel = ucfirst($riskLevel);
    $riskTone = match ($riskLevel) {
        'critical' => [
            'badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
            'dot' => 'bg-rose-500',
            'ring' => 'ring-rose-500/20',
            'panel' => 'ai-prediction-panel--critical',
        ],
        'warning' => [
            'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
            'dot' => 'bg-amber-500',
            'ring' => 'ring-amber-500/20',
            'panel' => 'ai-prediction-panel--warning',
        ],
        default => [
            'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
            'dot' => 'bg-emerald-500',
            'ring' => 'ring-emerald-500/20',
            'panel' => 'ai-prediction-panel--normal',
        ],
    };
@endphp

<section class="ai-prediction-panel {{ $riskTone['panel'] }} ring-1 {{ $riskTone['ring'] }}">
    <div class="grid gap-6 p-5 lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.4fr)] lg:p-6">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-400 dark:text-slate-500">AI predictive maintenance</p>
                    <h2 class="mt-2 font-display text-xl font-semibold tracking-[-0.04em] text-slate-950 dark:text-white sm:text-2xl">
                        Isolation Forest readiness
                    </h2>
                </div>

                <span class="app-pill {{ $riskTone['badge'] }}">
                    <span class="mr-2 inline-flex h-2 w-2 rounded-full {{ $riskTone['dot'] }}"></span>
                    {{ $latestAiPrediction ? $riskLabel : 'Awaiting AI' }}
                </span>
            </div>

            <p class="mt-4 text-sm leading-7 text-slate-500 dark:text-slate-400">
                Laravel stores and displays predictions received from n8n. The Python Isolation Forest model runs outside the production hosting environment.
            </p>

            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="ai-prediction-tile">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Rack health</p>
                    <strong>
                        {{ $latestAiPrediction ? $latestAiPrediction->rack_health_score.'%' : '--' }}
                    </strong>
                </div>

                <div class="ai-prediction-tile">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Failure probability</p>
                    <strong>
                        {{ $latestAiPrediction ? $latestAiPrediction->failure_probability.'%' : '--' }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="ai-prediction-detail min-w-0 rounded-3xl sm:p-5">
            @if ($latestAiPrediction)
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Temperature</p>
                        <p class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">
                            {{ $latestAiPrediction->temperature === null ? 'Unavailable' : number_format($latestAiPrediction->temperature, 1).' deg C' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Power usage</p>
                        <p class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">
                            {{ $latestAiPrediction->power_w === null ? 'Unavailable' : number_format($latestAiPrediction->power_w, 0).' W' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Anomaly score</p>
                        <p class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">
                            {{ $latestAiPrediction->anomaly_score === null ? 'Unavailable' : number_format($latestAiPrediction->anomaly_score, 2) }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 space-y-4 text-sm leading-7">
                    <div>
                        <p class="font-semibold text-slate-950 dark:text-white">Main cause</p>
                        <p class="text-slate-500 dark:text-slate-400">{{ $latestAiPrediction->main_cause ?: 'Not provided' }}</p>
                    </div>

                    <div>
                        <p class="font-semibold text-slate-950 dark:text-white">Explanation</p>
                        <p class="text-slate-500 dark:text-slate-400">{{ $latestAiPrediction->explanation ?: 'Not provided' }}</p>
                    </div>

                    <div>
                        <p class="font-semibold text-slate-950 dark:text-white">Recommendation</p>
                        <p class="text-slate-500 dark:text-slate-400">{{ $latestAiPrediction->recommendation ?: 'Not provided' }}</p>
                    </div>
                </div>

                <p class="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">
                    Last AI update {{ $latestAiPrediction->created_at?->diffForHumans() }}
                </p>
            @else
                <div class="flex h-full min-h-[220px] flex-col justify-center text-center">
                    <p class="font-display text-2xl font-semibold tracking-[-0.04em] text-slate-950 dark:text-white">Waiting for first AI prediction</p>
                    <p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-slate-500 dark:text-slate-400">
                        After n8n posts an Isolation Forest result to Laravel, this section will show the latest risk level, rack health score, cause, explanation, and recommendation.
                    </p>
                </div>
            @endif
        </div>
    </div>
</section>
