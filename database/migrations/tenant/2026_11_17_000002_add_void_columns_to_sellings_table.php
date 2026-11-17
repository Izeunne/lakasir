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
            if (! Schema::hasColumn('sellings', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('note');
            }
            if (! Schema::hasColumn('sellings', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('sellings', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellings', function (Blueprint $table) {
            if (Schema::hasColumn('sellings', 'voided_by')) {
                $table->dropConstrainedForeignId('voided_by');
            }
            if (Schema::hasColumn('sellings', 'void_reason')) {
                $table->dropColumn('void_reason');
            }
            if (Schema::hasColumn('sellings', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
