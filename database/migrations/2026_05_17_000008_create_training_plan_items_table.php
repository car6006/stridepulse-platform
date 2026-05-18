<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_plan_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('training_plan_id');
            $table->unsignedBigInteger('workout_id')->nullable();
            $table->date('scheduled_for');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('training_plan_id')->references('id')->on('training_plans')->onDelete('cascade');
            $table->foreign('workout_id')->references('id')->on('workouts')->onDelete('set null');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('training_plan_items');
    }
};
