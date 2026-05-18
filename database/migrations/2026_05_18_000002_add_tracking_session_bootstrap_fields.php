<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tracking_sessions', function (Blueprint $table) {
            $table->foreignId('sport_id')->nullable()->after('athlete_id')->constrained()->nullOnDelete();
            $table->string('device_source')->nullable()->after('race_entry_id');
            $table->string('activity_type')->nullable()->after('device_source');
            $table->string('status')->default('active')->after('activity_type');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_sessions', function (Blueprint $table) {
            $table->dropForeign(['sport_id']);
            $table->dropColumn([
                'sport_id',
                'device_source',
                'activity_type',
                'status',
            ]);
        });
    }
};
