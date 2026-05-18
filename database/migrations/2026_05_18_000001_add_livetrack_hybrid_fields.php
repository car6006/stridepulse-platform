<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tracking_sessions', function (Blueprint $table) {
            $table->text('livetrack_url')->nullable()->after('ended_at');
            $table->timestamp('livetrack_received_at')->nullable()->after('livetrack_url');
            $table->string('livetrack_source_email')->nullable()->after('livetrack_received_at');
            $table->string('telemetry_source')->default('connect_iq')->after('livetrack_source_email');
            $table->timestamp('last_direct_telemetry_at')->nullable()->after('telemetry_source');
        });

        Schema::create('livetrack_inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('athlete_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_alias')->nullable()->index();
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->longText('raw_body');
            $table->text('extracted_url')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default('received');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livetrack_inbound_messages');

        Schema::table('tracking_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'livetrack_url',
                'livetrack_received_at',
                'livetrack_source_email',
                'telemetry_source',
                'last_direct_telemetry_at',
            ]);
        });
    }
};
