<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('garmin_connections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('athlete_id');
            $table->string('external_id');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('athlete_id')->references('id')->on('athletes')->onDelete('cascade');
            $table->unique(['athlete_id', 'external_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('garmin_connections');
    }
};
