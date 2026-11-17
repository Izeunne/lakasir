<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            if (! Schema::hasColumn('printers', 'print_on_draft')) {
                $table->boolean('print_on_draft')->default(false)->after('show_header');
            }
        });

        // Set default print_on_draft = true untuk semua printer checker (dapur/bar) yang show_price = false
        if (Schema::hasTable('printers') && Schema::hasColumn('printers', 'print_on_draft')) {
            DB::table('printers')->where('show_price', false)->update(['print_on_draft' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            if (Schema::hasColumn('printers', 'print_on_draft')) {
                $table->dropColumn('print_on_draft');
            }
        });
    }
};
