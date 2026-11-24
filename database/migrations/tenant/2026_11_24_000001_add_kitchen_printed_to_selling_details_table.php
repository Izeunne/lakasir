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
        Schema::table('selling_details', function (Blueprint $table) {
            $table->boolean('is_kitchen_printed')->default(false)->after('note');
            $table->timestamp('kitchen_printed_at')->nullable()->after('is_kitchen_printed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('selling_details', function (Blueprint $table) {
            $table->dropColumn(['is_kitchen_printed', 'kitchen_printed_at']);
        });
    }
};
