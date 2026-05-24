<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiPrediction;
use App\Models\PowerReading;
use App\Models\SensorReading;
use Illuminate\Http\JsonResponse;

class PublicTelemetryController extends Controller
{
    public function climateLatest(): JsonResponse
    {
        $reading = SensorReading::query()
            ->latest('recorded_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'temperature' => $reading ? [
                    'value' => round((float) $reading->temperature, 1),
                    'status' => $this->temperatureStatus((float) $reading->temperature),
                    'recorded_at' => optional($reading->recorded_at)->toIso8601String(),
                ] : null,
                'humidity' => $reading ? [
                    'value' => $reading->humidity === null ? null : round((float) $reading->humidity, 1),
                    'status' => $reading->humidity === null ? 'awaiting' : $this->humidityStatus((float) $reading->humidity),
                    'recorded_at' => optional($reading->recorded_at)->toIso8601String(),
                ] : null,
                'air_flow' => [
                    'value' => null,
                    'status' => 'awaiting',
                    'recorded_at' => null,
                ],
            ],
        ]);
    }

    public function powerLatest(): JsonResponse
    {
        $reading = PowerReading::query()
            ->latest('created_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $reading ? [
                'power_w' => round((float) $reading->power_w, 1),
                'voltage_v' => $reading->voltage_v === null ? null : round((float) $reading->voltage_v, 1),
                'current_a' => $reading->current_a === null ? null : round((float) $reading->current_a, 2),
                'status' => $this->powerStatus((float) $reading->power_w),
                'recorded_at' => optional($reading->created_at)->toIso8601String(),
            ] : null,
        ]);
    }

    public function aiPredictionLatest(): JsonResponse
    {
        $prediction = AiPrediction::query()
            ->latest('created_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $prediction ? [
                'risk_level' => $prediction->risk_level,
                'rack_health_score' => $prediction->rack_health_score,
                'failure_probability' => $prediction->failure_probability,
                'temperature' => $prediction->temperature,
                'power_w' => $prediction->power_w,
                'anomaly_score' => $prediction->anomaly_score,
                'main_cause' => $prediction->main_cause,
                'explanation' => $prediction->explanation,
                'recommendation' => $prediction->recommendation,
                'created_at' => optional($prediction->created_at)->toIso8601String(),
            ] : null,
        ]);
    }

    private function temperatureStatus(float $temperature): string
    {
        return match (true) {
            $temperature >= 30 => 'critical',
            $temperature >= 25 => 'warning',
            default => 'stable',
        };
    }

    private function humidityStatus(float $humidity): string
    {
        return match (true) {
            $humidity >= 70 || $humidity <= 30 => 'critical',
            $humidity >= 60 || $humidity <= 35 => 'warning',
            default => 'stable',
        };
    }

    private function powerStatus(float $power): string
    {
        return match (true) {
            $power >= 900 => 'critical',
            $power >= 700 => 'warning',
            default => 'stable',
        };
    }
}
