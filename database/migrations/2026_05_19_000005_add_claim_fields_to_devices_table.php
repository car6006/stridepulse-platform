<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('devices', 'device_uuid')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->string('device_uuid')->nullable()->unique()->after('uuid');
            });
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['athlete_id']);
            $table->unsignedBigInteger('athlete_id')->nullable()->change();
            $table->foreign('athlete_id')->references('id')->on('athletes')->cascadeOnDelete();
        });

        DB::table('devices')
            ->whereNull('device_uuid')
            ->update(['device_uuid' => DB::raw('uuid')]);
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['athlete_id']);
            $table->unsignedBigInteger('athlete_id')->nullable(false)->change();
            $table->foreign('athlete_id')->references('id')->on('athletes')->cascadeOnDelete();
            if (Schema::hasColumn('devices', 'device_uuid')) {
                $table->dropUnique(['device_uuid']);
                $table->dropColumn('device_uuid');
            }
        });
    }
};
