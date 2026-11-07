<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('cart_drafts') && ! Schema::hasColumn('cart_drafts', 'note')) {
            Schema::table('cart_drafts', function (Blueprint $table) {
                $table->text('note')->nullable()->after('discount_price');
            });
        }

        if (Schema::hasTable('roles')) {
            $kepalaToko = Role::firstOrCreate(['name' => 'kepala toko', 'guard_name' => 'web']);
            $kasir = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);

            if (Schema::hasTable('permissions')) {
                $storePinPerm = Permission::firstOrCreate(['name' => 'access store pin', 'guard_name' => 'web']);
                $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
                if ($adminRole) {
                    $adminRole->givePermissionTo($storePinPerm);
                }

                $kepalaTokoPerms = Permission::where('guard_name', 'web')
                    ->whereIn('name', [
                        'access web app',
                        'access store pin',
                        'create selling',
                        'read selling',
                        'update selling',
                        'delete selling',
                        'can print selling',
                        'read product',
                        'create product',
                        'update product',
                        'read category',
                        'read member',
                        'create member',
                        'update member',
                        'access report',
                        'generate selling report',
                        'generate cashier report',
                        'generate product report',
                    ])->get();
                $kepalaToko->syncPermissions($kepalaTokoPerms);

                $kasirPerms = Permission::where('guard_name', 'web')
                    ->whereIn('name', [
                        'access web app',
                        'create selling',
                        'read selling',
                        'update selling',
                        'can print selling',
                        'read product',
                        'read category',
                        'read member',
                        'create member',
                    ])->get();
                $kasir->syncPermissions($kasirPerms);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cart_drafts') && Schema::hasColumn('cart_drafts', 'note')) {
            Schema::table('cart_drafts', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
};

