<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('print_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id')->default(1)->index();
            $table->string('queue_type')->default('cashier')->index(); // 'cashier' | 'kitchen'
            $table->string('station_id')->nullable()->index();
            $table->string('device_id')->nullable()->index();
            $table->unsignedBigInteger('selling_id')->nullable()->index();
            $table->string('status')->default('queued')->index(); // 'queued' | 'processing' | 'completed' | 'failed'
            $table->string('type')->default('receipt'); // 'receipt' | 'kitchen_ticket' | 'raw'
            $table->string('paper_width')->default('58mm');
            $table->json('payload')->nullable();
            $table->json('layout')->nullable();
            $table->json('transaction')->nullable();
            $table->json('ticket')->nullable();
            $table->longText('raw_base64')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_queue');
    }
};

