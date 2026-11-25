<?php

use App\Models\AiPrediction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$destination = storage_path('app/pages');
if (! File::exists($destination.'/build/manifest.json')) {
    throw new RuntimeException('Run npm run build:pages before exporting the dashboard.');
}

File::copy(public_path('favicon.ico'), $destination.'/favicon.ico');
File::put($destination.'/.nojekyll', '');
$app->usePublicPath($destination);
Vite::createAssetPathsUsing(static fn (string $path): string => './'.$path);

// Realistic 24-hour fine-grained telemetry samples in Tunisia time
$now = CarbonImmutable::now('Africa/Tunis');
$sampleStart = $now->subHours(24);
$sampleCount = 36; // Every 40 minutes over the last 24 hours

$sampleTimes = [];
$temperature = [];
$humidity = [];
$power = [];

for ($i = 0; $i < $sampleCount; $i++) {
    $time = $sampleStart->addMinutes((int) round(($i * 24 * 60) / ($sampleCount - 1)));
    $sampleTimes[] = $time;
    $hour = (int) $time->format('G');

    // Diurnal temperature drift
    $temp = 22.2 + sin((($hour - 8) / 24) * 2 * M_PI) * 0.6 + (((($i * 13) % 17) - 8) * 0.04);
    $hum = 46.5 - sin((($hour - 8) / 24) * 2 * M_PI) * 1.2 + (((($i * 19) % 23) - 11) * 0.06);
    $load = ($hour >= 8 && $hour <= 18) ? 1.18 : 0.96;
    $pw = (1020 * $load) + (((($i * 29) % 37) - 18) * 4.2);

    $temperature[] = round($temp, 1);
    $humidity[] = round($hum, 1);
    $power[] = round($pw, 1);
}

$lastSample = end($sampleTimes);
$lastTemperature = end($temperature);
$lastHumidity = end($humidity);
$lastPower = end($power);
$labels = array_map(fn (CarbonImmutable $time): string => $time->format('H:i'), $sampleTimes);

$serverCard = [
    'id' => 'srv-node-01',
    'name' => 'SRV-INFRAGUARD-01',
    'identifier' => 'Primary Server Room Workstation & Gateway',
    'status' => 'Live',
    'statusColor' => 'emerald',
    'lastSeenAt' => $lastSample->toDateTimeString(),
    'lastSeenLabel' => 'Updated '.$lastSample->format('H:i:s'),
    'narrative' => 'Node is healthy. Hardware temperatures and system resources are within nominal operating limits.',
    'metrics' => [
        [
            'key' => 'cpu',
            'label' => 'CPU usage',
            'value' => '24.2%',
            'progress' => 24,
            'color' => 'cyan',
            'footerLabel' => 'Utilization',
            'progressLabel' => '24%',
        ],
        [
            'key' => 'ram',
            'label' => 'Memory usage',
            'value' => '14.2 / 32.0 GB',
            'progress' => 44,
            'color' => 'violet',
            'footerLabel' => 'Physical RAM',
            'progressLabel' => '44%',
        ],
        [
            'key' => 'disk',
            'label' => 'Primary disk',
            'value' => '412.5 / 1000.0 GB',
            'progress' => 41,
            'color' => 'amber',
            'footerLabel' => 'System drive',
            'progressLabel' => '41%',
        ],
        [
            'key' => 'network',
            'label' => 'Active interface',
            'value' => '10 Gbps',
            'progress' => 38,
            'color' => 'emerald',
            'footerLabel' => 'eth0 (10GbE SFP+)',
            'progressLabel' => 'Online',
        ],
    ],
    'details' => [
        'storage' => [
            'label' => 'Total storage',
            'value' => '412.5 / 1000.0 GB',
            'progress' => 41,
            'state' => 'Optimal',
            'badge' => 'Optimal',
            'badgeClass' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
            'freePercent' => '58.7% free',
        ],
        'temperature' => [
            'label' => 'Hardware thermal probe',
            'value' => '41.2 °C',
            'progress' => 41,
            'state' => 'Optimal',
            'badge' => 'Optimal',
            'badgeClass' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
            'footer' => 'AC cooling intake nominal',
        ],
        'network' => [
            'label' => 'Network status',
            'value' => 'Ethernet 10Gbps Duplex',
            'badge' => 'Online',
            'badgeClass' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
            'rxLabel' => 'Inbound',
            'rxValue' => '24.8 Mbps',
            'txLabel' => 'Outbound',
            'txValue' => '32.1 Mbps',
            'ipv4' => '192.168.1.10',
        ],
    ],
];

$html = view('dashboard', [
    'pagesPreview' => true,
    'temperatureData' => ['value' => $lastTemperature, 'status' => 'Stable', 'ringDegrees' => 148],
    'humidityData' => ['value' => $lastHumidity, 'status' => 'Stable', 'ringDegrees' => 168],
    'trendData' => [
        'hasData' => true,
        'labels' => $labels,
        'tooltipLabels' => array_map(fn (CarbonImmutable $time): string => $time->format('d M Y, H:i'), $sampleTimes),
        'temperature' => $temperature,
        'humidity' => $humidity,
        'power' => $power,
        'powerLabels' => $labels,
        'latest' => [
            'fullLabel' => $lastSample->format('d M Y, H:i'),
            'power_reading' => [
                'voltage_v' => 231.2,
                'current_a' => 4.82,
                'power_w' => $lastPower,
                'created_at' => $lastSample->toIso8601String(),
            ],
        ],
    ],
    'servers' => [$serverCard],
    'latestAiPrediction' => new AiPrediction([
        'temperature' => $lastTemperature,
        'power_w' => $lastPower,
        'risk_level' => 'normal',
        'rack_health_score' => 96,
        'failure_probability' => 3,
        'anomaly_score' => 0.06,
        'main_cause' => 'Nominal operational envelope',
        'explanation' => 'Thermal distribution, airflow velocity, and power consumption are operating well within ASHRAE TC 9.9 server room specifications.',
        'recommendation' => 'All environmental parameters are healthy. Maintain standard monitoring cadence.',
    ]),
])->render();

File::put($destination.'/index.html', $html);
