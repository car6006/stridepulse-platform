<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('telemetry_points', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tracking_session_id');
            $table->string('ingestion_id')->nullable();
            $table->timestamp('recorded_at');
            $table->jsonb('raw_payload');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tracking_session_id')->references('id')->on('tracking_sessions')->onDelete('cascade');
            $table->index(['tracking_session_id', 'ingestion_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('telemetry_points');
    }
};
