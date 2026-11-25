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
$app->usePublicPath($destination);
Vite::createAssetPathsUsing(static fn (string $path): string => './'.$path);

$temperature = [21.8, 22.1, 22.4, 23.7, 24.1, 23.8, 25.2, 25.6, 24.9, 24.6, 25.1, 25.4];
$humidity = [45.8, 46.4, 46.2, 47.0, 48.3, 47.8, 48.9, 50.2, 49.6, 48.4, 47.9, 48.7];
$power = [842, 856, 889, 911, 926, 945, 1008, 1031, 996, 983, 1014, 1024];
$sampleStart = CarbonImmutable::parse('2026-05-21 10:00', 'Africa/Tunis');
$sampleTimes = array_map(
    fn (int $index): CarbonImmutable => $sampleStart->addMinutes(5 * $index),
    array_keys($temperature),
);
$lastSample = end($sampleTimes);
$lastTemperature = end($temperature);
$lastHumidity = end($humidity);
$lastPower = end($power);
$labels = array_map(fn (CarbonImmutable $time): string => $time->format('H:i'), $sampleTimes);

$html = view('dashboard', [
    'pagesPreview' => true,
    'temperatureData' => ['value' => $lastTemperature, 'status' => 'Warning', 'ringDegrees' => 185],
    'humidityData' => ['value' => $lastHumidity, 'status' => 'Stable', 'ringDegrees' => 175],
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
                'voltage_v' => 232.1,
                'current_a' => 4.41,
                'power_w' => $lastPower,
                'created_at' => $lastSample->toIso8601String(),
            ],
        ],
    ],
    'servers' => [],
    'latestAiPrediction' => new AiPrediction([
        'temperature' => $lastTemperature,
        'power_w' => $lastPower,
        'risk_level' => 'warning',
        'rack_health_score' => 84,
        'failure_probability' => 16,
        'anomaly_score' => 0.27,
        'main_cause' => 'Elevated rack temperature',
        'explanation' => 'Temperature moved above the target band while power draw increased.',
        'recommendation' => 'Check inlet airflow and compare the affected rack with adjacent racks.',
    ]),
])->render();

File::put($destination.'/index.html', $html);
