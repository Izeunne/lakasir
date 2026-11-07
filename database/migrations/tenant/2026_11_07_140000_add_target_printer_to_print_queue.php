<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('print_queue') && !Schema::hasColumn('print_queue', 'target_printer')) {
            Schema::table('print_queue', function (Blueprint $table) {
                $table->string('target_printer', 100)->nullable()->after('queue_type')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('print_queue') && Schema::hasColumn('print_queue', 'target_printer')) {
            Schema::table('print_queue', function (Blueprint $table) {
                $table->dropColumn('target_printer');
            });
        }
    }
};

