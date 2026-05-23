<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiPrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'temperature',
        'power_w',
        'risk_level',
        'rack_health_score',
        'failure_probability',
        'anomaly_score',
        'main_cause',
        'explanation',
        'recommendation',
        'raw_prediction',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'power_w' => 'float',
            'rack_health_score' => 'integer',
            'failure_probability' => 'integer',
            'anomaly_score' => 'float',
            'raw_prediction' => 'array',
        ];
    }
}
