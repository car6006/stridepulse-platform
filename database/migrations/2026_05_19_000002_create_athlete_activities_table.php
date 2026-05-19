<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('athlete_activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('connect_iq');
            $table->string('status')->default('completed');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('distance_m', 10, 2)->nullable();
            $table->unsignedInteger('average_pace_sec_per_km')->nullable();
            $table->unsignedSmallInteger('average_heart_rate_bpm')->nullable();
            $table->unsignedSmallInteger('max_heart_rate_bpm')->nullable();
            $table->unsignedInteger('calories')->nullable();
            $table->decimal('ascent_m', 10, 2)->nullable();
            $table->decimal('descent_m', 10, 2)->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->jsonb('summary_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_activities');
    }
};
