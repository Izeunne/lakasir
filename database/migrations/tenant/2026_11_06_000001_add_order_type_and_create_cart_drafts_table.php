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
        Schema::table('sellings', function (Blueprint $table) {
            $table->string('order_type')->nullable()->after('table_id');
        });

        Schema::create('cart_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('order_type');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('table_id')->nullable();
            $table->string('voucher')->nullable();
            $table->double('discount_price')->default(0);
            $table->double('total_price')->default(0);
            $table->json('items');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_drafts');

        Schema::table('sellings', function (Blueprint $table) {
            $table->dropColumn('order_type');
        });
    }
};

