<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('telemetry_points', function (Blueprint $table) {
            $table->unsignedInteger('elapsed_time_seconds')->nullable()->after('elapsed_seconds');
            $table->unsignedInteger('average_pace_sec_per_km')->nullable()->after('pace_sec_per_km');
            $table->decimal('current_speed_mps', 8, 3)->nullable()->after('average_pace_sec_per_km');
            $table->decimal('altitude_m', 10, 2)->nullable()->after('longitude');
            $table->decimal('heading_degrees', 6, 2)->nullable()->after('altitude_m');
            $table->decimal('ascent_m', 10, 2)->nullable()->after('heading_degrees');
            $table->decimal('descent_m', 10, 2)->nullable()->after('ascent_m');
            $table->unsignedInteger('calories')->nullable()->after('descent_m');
            $table->unsignedInteger('lap_number')->nullable()->after('calories');
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_points', function (Blueprint $table) {
            $table->dropColumn([
                'elapsed_time_seconds',
                'average_pace_sec_per_km',
                'current_speed_mps',
                'altitude_m',
                'heading_degrees',
                'ascent_m',
                'descent_m',
                'calories',
                'lap_number',
            ]);
        });
    }
};
