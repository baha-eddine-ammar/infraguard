<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiPrediction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiPredictionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $configuredToken = (string) config('sensors.api_token');
        $providedToken = (string) $request->header('X-Sensor-Token');

        if ($configuredToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'message' => 'Invalid AI prediction credentials.',
            ], 401);
        }

        $validated = $request->validate([
            'temperature' => ['nullable', 'numeric'],
            'power_w' => ['nullable', 'numeric'],
            'risk_level' => ['required', Rule::in(['normal', 'warning', 'critical'])],
            'rack_health_score' => ['required', 'integer', 'between:0,100'],
            'failure_probability' => ['required', 'integer', 'between:0,100'],
            'anomaly_score' => ['nullable', 'numeric'],
            'main_cause' => ['nullable', 'string', 'max:255'],
            'explanation' => ['nullable', 'string'],
            'recommendation' => ['nullable', 'string'],
            'raw_prediction' => ['nullable', 'array'],
        ]);

        $prediction = AiPrediction::query()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'AI prediction saved successfully',
            'data' => $prediction,
        ], 201);
    }
}
