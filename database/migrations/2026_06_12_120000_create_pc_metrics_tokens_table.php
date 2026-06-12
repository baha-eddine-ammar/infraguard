<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_metrics_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('token_hash', 64);
            $table->string('token_prefix', 16);
            $table->string('token_suffix', 16);
            $table->timestamp('last_rotated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_metrics_tokens');
    }
};
