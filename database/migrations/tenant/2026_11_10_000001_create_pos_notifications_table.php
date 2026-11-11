<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id')->nullable()->index();
            $table->string('device_id')->nullable()->index();
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('order'); // order, alert, info, warning, kitchen, system
            $table->string('sound')->default('default');
            $table->json('data')->nullable();
            $table->string('status')->default('pending')->index(); // pending, delivered, read
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_notifications');
    }
};

