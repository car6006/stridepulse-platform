<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tracking_sessions', function (Blueprint $table) {
            $table->string('session_token')->nullable()->unique()->after('uuid');
            $table->timestamp('ended_at')->nullable()->after('last_seen_at');
        });

        Schema::table('telemetry_points', function (Blueprint $table) {
            $table->unsignedInteger('elapsed_seconds')->nullable()->after('recorded_at');
            $table->decimal('distance_m', 10, 2)->nullable()->after('elapsed_seconds');
            $table->unsignedInteger('pace_sec_per_km')->nullable()->after('distance_m');
            $table->unsignedSmallInteger('heart_rate_bpm')->nullable()->after('pace_sec_per_km');
            $table->unsignedSmallInteger('avg_heart_rate_bpm')->nullable()->after('heart_rate_bpm');
            $table->unsignedSmallInteger('cadence')->nullable()->after('avg_heart_rate_bpm');
            $table->decimal('latitude', 10, 7)->nullable()->after('cadence');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('gps_status')->nullable()->after('longitude');
            $table->unsignedTinyInteger('battery_percent')->nullable()->after('gps_status');
            $table->string('device_model')->nullable()->after('battery_percent');
            $table->unique(['tracking_session_id', 'ingestion_id']);
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_points', function (Blueprint $table) {
            $table->dropUnique(['tracking_session_id', 'ingestion_id']);
            $table->dropColumn([
                'elapsed_seconds',
                'distance_m',
                'pace_sec_per_km',
                'heart_rate_bpm',
                'avg_heart_rate_bpm',
                'cadence',
                'latitude',
                'longitude',
                'gps_status',
                'battery_percent',
                'device_model',
            ]);
        });

        Schema::table('tracking_sessions', function (Blueprint $table) {
            $table->dropUnique(['session_token']);
            $table->dropColumn(['session_token', 'ended_at']);
        });
    }
};
