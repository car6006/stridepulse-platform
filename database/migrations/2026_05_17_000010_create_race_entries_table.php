<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('race_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('athlete_id');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('athlete_id')->references('id')->on('athletes')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('race_entries');
    }
};
