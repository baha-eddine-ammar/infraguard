<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_predictions', function (Blueprint $table): void {
            $table->id();
            $table->float('temperature')->nullable();
            $table->float('power_w')->nullable();
            $table->enum('risk_level', ['normal', 'warning', 'critical'])->default('normal')->index();
            $table->unsignedTinyInteger('rack_health_score')->default(100);
            $table->unsignedTinyInteger('failure_probability')->default(0);
            $table->float('anomaly_score')->nullable();
            $table->string('main_cause')->nullable();
            $table->text('explanation')->nullable();
            $table->text('recommendation')->nullable();
            $table->json('raw_prediction')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_predictions');
    }
};
