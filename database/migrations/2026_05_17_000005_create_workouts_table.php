<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('athlete_id');
            $table->unsignedBigInteger('sport_id');
            $table->string('name');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('athlete_id')->references('id')->on('athletes')->onDelete('cascade');
            $table->foreign('sport_id')->references('id')->on('sports')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('workouts');
    }
};
