<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (! Schema::hasColumn('devices', 'pairing_code')) {
                $table->string('pairing_code', 6)->nullable()->after('device_uuid')->index();
            }

            if (! Schema::hasColumn('devices', 'last_claimed_at')) {
                $table->timestamp('last_claimed_at')->nullable()->after('last_seen_at');
            }

            if (! Schema::hasColumn('devices', 'last_telemetry_at')) {
                $table->timestamp('last_telemetry_at')->nullable()->after('last_claimed_at');
            }

            if (! Schema::hasColumn('devices', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('last_telemetry_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'pairing_code')) {
                $table->dropIndex(['pairing_code']);
                $table->dropColumn('pairing_code');
            }

            foreach (['last_claimed_at', 'last_telemetry_at', 'archived_at'] as $column) {
                if (Schema::hasColumn('devices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
