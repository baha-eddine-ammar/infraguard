<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PowerReading;
use App\Models\SensorReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiInputController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $configuredToken = (string) config('sensors.api_token');
        $providedToken = (string) $request->header('X-Sensor-Token');

        if ($configuredToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid AI input credentials.',
            ], 401);
        }

        $temperatureReading = SensorReading::query()
            ->latest('recorded_at')
            ->first();

        $powerReading = PowerReading::query()
            ->latest('created_at')
            ->first();

        if (! $temperatureReading || ! $powerReading) {
            return response()->json([
                'success' => false,
                'message' => 'AI input data is not ready yet.',
                'missing' => [
                    'temperature' => ! $temperatureReading,
                    'power_w' => ! $powerReading,
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'temperature' => round((float) $temperatureReading->temperature, 2),
                'power_w' => round((float) $powerReading->power_w, 2),
                'temperature_recorded_at' => optional($temperatureReading->recorded_at)->toIso8601String(),
                'power_recorded_at' => optional($powerReading->created_at)->toIso8601String(),
            ],
        ]);
    }
}
