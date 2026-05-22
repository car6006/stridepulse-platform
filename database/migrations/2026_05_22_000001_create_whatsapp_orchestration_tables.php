<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supporters', function (Blueprint $table) {
            if (! Schema::hasColumn('supporters', 'phone_number')) {
                $table->string('phone_number')->nullable()->unique()->after('name');
            }
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('athlete_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number')->unique();
            $table->string('profile_name')->nullable();
            $table->string('state')->default('idle');
            $table->jsonb('context')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamps();

            $table->index(['athlete_id', 'state']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('whatsapp_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction');
            $table->string('phone_number');
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('message_type')->default('text');
            $table->text('body')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['phone_number', 'direction']);
        });

        Schema::create('whatsapp_message_dispatches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('whatsapp_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tracking_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number');
            $table->string('template_name')->nullable();
            $table->text('body')->nullable();
            $table->string('dedupe_key')->unique();
            $table->string('status')->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->jsonb('payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['phone_number', 'status']);
            $table->index(['tracking_session_id', 'template_name']);
        });

        Schema::create('supporter_invitations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supporter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number');
            $table->string('status')->default('pending');
            $table->string('source')->default('whatsapp_trackme');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'phone_number']);
            $table->index(['phone_number', 'status']);
        });

        Schema::create('supporter_consents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('supporter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supporter_invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number');
            $table->string('status');
            $table->string('source')->default('whatsapp_reply');
            $table->text('response_text')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->jsonb('audit_payload')->nullable();
            $table->timestamps();

            $table->index(['phone_number', 'status']);
            $table->index(['event_id', 'status']);
        });

        Schema::create('event_followers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supporter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supporter_consent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number');
            $table->string('status')->default('active');
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'phone_number']);
            $table->index(['athlete_id', 'status']);
        });

        Schema::create('event_checkpoints', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('distance_m', 10, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_m')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'distance_m']);
        });

        Schema::create('telemetry_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('tracking_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('alert_type');
            $table->string('dedupe_key')->unique();
            $table->jsonb('payload')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->index(['tracking_session_id', 'alert_type']);
        });

        Schema::create('event_estimations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('tracking_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('distance_m', 10, 2)->nullable();
            $table->decimal('remaining_distance_m', 10, 2)->nullable();
            $table->unsignedInteger('average_pace_sec_per_km')->nullable();
            $table->timestamp('estimated_finish_at')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            $table->index(['tracking_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_estimations');
        Schema::dropIfExists('telemetry_alerts');
        Schema::dropIfExists('event_checkpoints');
        Schema::dropIfExists('event_followers');
        Schema::dropIfExists('supporter_consents');
        Schema::dropIfExists('supporter_invitations');
        Schema::dropIfExists('whatsapp_message_dispatches');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');

        Schema::table('supporters', function (Blueprint $table) {
            if (Schema::hasColumn('supporters', 'phone_number')) {
                $table->dropUnique(['phone_number']);
                $table->dropColumn('phone_number');
            }
        });
    }
};
