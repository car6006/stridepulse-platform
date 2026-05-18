<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supporter_id');
            $table->string('channel');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('supporter_id')->references('id')->on('supporters')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('notification_subscriptions');
    }
};
