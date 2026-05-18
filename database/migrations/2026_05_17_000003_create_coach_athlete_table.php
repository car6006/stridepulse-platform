<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_athlete', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('athlete_id');
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('cascade');
            $table->foreign('athlete_id')->references('id')->on('athletes')->onDelete('cascade');
            $table->unique(['coach_id', 'athlete_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('coach_athlete');
    }
};
