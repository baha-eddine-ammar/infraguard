<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerRequest;
use App\Models\Server;
use App\Services\DefaultPcServerService;
use App\Services\PcMetricsTokenService;
use App\Services\ServerMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServerController extends Controller
{
    private const SERVER_SECTION_METRIC_KEYS = ['cpu', 'ram', 'disk', 'network'];

    public function __construct(
        private readonly ServerMonitoringService $serverMonitoringService,
        private readonly DefaultPcServerService $defaultPcServerService,
        private readonly PcMetricsTokenService $pcMetricsTokenService,
    ) {
    }

    public function index(): View
    {
        $server = $this->defaultPcServerService->resolve();
        $this->loadNewestMetric($server);

        return view('servers.index', [
            'serverCard' => $this->serverSectionCard($server),
            'serverStateFeedUrl' => route('servers.live-state'),
            'pcMetricsToken' => $this->pcMetricsTokenService->settingsSummary(),
        ]);
    }

    public function liveState(): JsonResponse
    {
        $server = $this->defaultPcServerService->resolve();
        $this->loadNewestMetric($server);

        return response()
            ->json([
                'server' => $this->serverSectionCard($server),
                'polled_at' => now()->toIso8601String(),
                'polling_interval_ms' => 5000,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    public function feed(Server $server): JsonResponse
    {
        $this->loadNewestMetric($server);

        return response()
            ->json($this->serverMonitoringService->buildCard($server))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    public function create(): View
    {
        return view('servers.create');
    }

    public function store(StoreServerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $server = Server::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'identifier' => $validated['identifier'],
            'ip_address' => $validated['ip_address'] ?? null,
            'server_type' => $validated['server_type'] ?? null,
            'api_token' => ($validated['api_token'] ?? null) ?: Str::random(40),
        ]);

        return redirect()
            ->route('servers.show', $server)
            ->with('success', 'Server created successfully. Copy the API token below into your monitoring agent.');
    }

    public function updatePcMetricsToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pc_metrics_token' => ['required', 'string', 'min:16', 'max:255'],
        ], [
            'pc_metrics_token.required' => 'Enter the PC metrics token used by the Windows collector.',
            'pc_metrics_token.min' => 'Use at least 16 characters for the PC metrics token.',
        ]);

        $this->pcMetricsTokenService->store($validated['pc_metrics_token']);

        return redirect()
            ->route('servers.index')
            ->with('success', 'PC metrics token updated. Paste the same token into the Windows collector token file.');
    }

    public function show(Server $server): View
    {
        $this->loadNewestMetric($server);

        $server->load([
            'metrics' => fn ($query) => $query->latest('id')->limit(10),
        ]);

        return view('servers.show', [
            'server' => $server,
            'serverCard' => $this->serverMonitoringService->buildCard($server),
            'feedUrl' => route('servers.feed', $server),
            'recentMetrics' => $server->metrics,
        ]);
    }

    private function serverSectionCard(Server $server): array
    {
        $card = $this->serverMonitoringService->buildCard($server);

        $card['metrics'] = collect($card['metrics'] ?? [])
            ->whereIn('key', self::SERVER_SECTION_METRIC_KEYS)
            ->values()
            ->all();

        if (isset($card['latestMetric']) && is_array($card['latestMetric'])) {
            $card['latestMetric'] = Arr::only($card['latestMetric'], [
                'cpu',
                'ram',
                'disk',
                'network',
                'lastSeen',
            ]);
        }

        return $card;
    }

    private function loadNewestMetric(Server $server): Server
    {
        $server->setRelation('latestMetric', $server->metrics()->latest('id')->first());

        return $server;
    }
}
