<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tracking_sessions', function (Blueprint $table) {
            $table->timestamp('last_movement_at')->nullable()->after('last_direct_telemetry_at');
            $table->timestamp('last_status_changed_at')->nullable()->after('last_movement_at');
            $table->timestamp('notification_suppressed_at')->nullable()->after('last_status_changed_at');
            $table->jsonb('notification_state')->nullable()->after('notification_suppressed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'last_movement_at',
                'last_status_changed_at',
                'notification_suppressed_at',
                'notification_state',
            ]);
        });
    }
};
