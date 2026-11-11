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
        Schema::table('printers', function (Blueprint $table) {
            if (!Schema::hasColumn('printers', 'driver')) {
                $table->string('driver')->default('bluetooth');
            } else {
                $table->string('driver')->default('bluetooth')->nullable()->change();
            }

            $table->string('device_id')->nullable()->after('driver')->index();
            $table->string('device_name')->nullable()->after('device_id');
            $table->string('connection_type')->default('bluetooth')->after('device_name'); // bluetooth, lan, usb, virtual
            $table->string('paper_width')->default('58mm')->after('connection_type');
            $table->boolean('print_all_categories')->default(true)->after('paper_width');
            $table->json('category_ids')->nullable()->after('print_all_categories');
            $table->boolean('show_price')->default(true)->after('category_ids');
            $table->text('header')->nullable()->after('show_price');
            $table->text('footer')->nullable()->after('header');
            $table->unsignedTinyInteger('copies')->default(1)->after('footer');
            $table->boolean('is_active')->default(true)->after('copies');
        });

        Schema::create('mobile_printers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id')->default(1)->index();
            $table->string('device_id')->index();
            $table->string('device_name');
            $table->string('connection_type')->default('bluetooth'); // bluetooth, lan, usb
            $table->string('address')->nullable();
            $table->string('paper_width')->default('58mm');
            $table->string('device_model')->nullable();
            $table->boolean('is_connected')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_printers');

        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn([
                'device_id',
                'device_name',
                'connection_type',
                'paper_width',
                'print_all_categories',
                'category_ids',
                'show_price',
                'header',
                'footer',
                'copies',
                'is_active',
            ]);
        });
    }
};

