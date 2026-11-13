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
        Schema::table('abouts', function (Blueprint $table) {
            if (! Schema::hasColumn('abouts', 'phone')) {
                $table->string('phone')->nullable()->after('shop_location');
            }
            if (! Schema::hasColumn('abouts', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('abouts', 'website')) {
                $table->string('website')->nullable()->after('email');
            }
            if (! Schema::hasColumn('abouts', 'npwp')) {
                $table->string('npwp')->nullable()->after('website');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('abouts', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['phone', 'email', 'website', 'npwp'] as $column) {
                if (Schema::hasColumn('abouts', $column)) {
                    $columnsToDrop[] = $column;
                }
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

