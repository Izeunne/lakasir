<?php

namespace Database\Seeders;

use App\Models\Tenants\About;
use App\Models\Tenants\Barcode;
use App\Models\Tenants\CartDraft;
use App\Models\Tenants\Category;
use App\Models\Tenants\Member;
use App\Models\Tenants\PaymentMethod;
use App\Models\Tenants\Product;
use App\Models\Tenants\ProductVariation;
use App\Models\Tenants\ProductVariationOption;
use App\Models\Tenants\Profile;
use App\Models\Tenants\Selling;
use App\Models\Tenants\SellingDetail;
use App\Models\Tenants\Setting;
use App\Models\Tenants\Stock;
use App\Models\Tenants\Table;
use App\Models\Tenants\User;
use App\Services\Tenants\CashierReportService;
use App\Services\Tenants\ProductReportService;
use App\Services\Tenants\SellingReportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PersonalPosSeeder extends Seeder
{
    /**
     * Seeder personal terpisah untuk mengisi data Category, Table, Product (+ Variasi & Stok),
     * Draft Pesanan, Selling History, serta memverifikasi Report.
     *
     * Jalankan dengan:
     * php artisan db:seed --class=Database\\Seeders\\PersonalPosSeeder
     */
    public function run(): void
    {
        DB::transaction(function () {
            $user = User::first() ?? User::create([
                'name' => 'Super Admin',
                'email' => 'superadmin@admin.com',
                'password' => Hash::make('password'),
                'is_owner' => true,
            ]);

            Profile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'timezone' => 'Asia/Makassar',
                    'locale' => 'id',
                ]
            );

            About::updateOrCreate(
                ['id' => About::first()?->id ?? 1],
                [
                    'shop_name' => 'Lakasir Resto & Cafe',
                    'shop_location' => 'Jl. Jenderal Sudirman No. 88',
                    'business_type' => 'fnb',
                ]
            );

            if (! Setting::get('store_pin')) {
                Setting::set('store_pin', Hash::make('123456'));
            }
            Setting::set('currency', 'IDR');

            $cashMethod = PaymentMethod::firstOrCreate(
                ['name' => 'Cash'],
                [
                    'is_cash' => true,
                    'is_debit' => false,
                    'is_credit' => false,
                    'is_wallet' => false,
                    'icon' => 'assets/images/payment-methods/cash.png',
                ]
            );

            $qrisMethod = PaymentMethod::firstOrCreate(
                ['name' => 'QRIS'],
                [
                    'is_cash' => false,
                    'is_debit' => false,
                    'is_credit' => false,
                    'is_wallet' => true,
                    'icon' => 'assets/images/payment-methods/qris.png',
                ]
            );

            $member = Member::firstOrCreate(
                ['code' => 'MBR-001'],
                [
                    'name' => 'Budi Santoso',
                    'email' => 'budi@example.com',
                    'address' => 'Jl. Merdeka No. 10',
                    'joined_date' => now()->subMonths(2),
                ]
            );

            // 1. Seed Categories
            $categoriesData = [
                'Makanan Utama',
                'Minuman Kopi & Teh',
                'Snack & Side Dish',
                'Dessert',
            ];
            $categories = [];
            foreach ($categoriesData as $catName) {
                $categories[$catName] = Category::firstOrCreate(['name' => $catName]);
            }

            // 2. Seed Tables (Meja)
            $tables = [];
            foreach (['Meja 01', 'Meja 02', 'Meja 03', 'Meja 04', 'Meja 05', 'VIP 01'] as $tableNumber) {
                $tables[$tableNumber] = Table::firstOrCreate(['number' => $tableNumber]);
            }

            // 3. Seed Products + Stocks + Variations
            $productsCatalog = [
                [
                    'sku' => 'PRD-NASGOR-01',
                    'barcode' => '899100100001',
                    'name' => 'Nasi Goreng Spesial',
                    'category' => 'Makanan Utama',
                    'unit' => 'Porsi',
                    'initial_price' => 14000,
                    'selling_price' => 25000,
                    'stock' => 100,
                    'variations' => [
                        [
                            'name' => 'Level Pedas',
                            'type' => 'single',
                            'is_required' => true,
                            'options' => [
                                ['name' => 'Tidak Pedas', 'price' => 0, 'is_default' => true],
                                ['name' => 'Sedang', 'price' => 0, 'is_default' => false],
                                ['name' => 'Pedas Ekstra', 'price' => 2000, 'is_default' => false],
                            ],
                        ],
                        [
                            'name' => 'Topping Tambahan',
                            'type' => 'multiple',
                            'is_required' => false,
                            'options' => [
                                ['name' => 'Telur Mata Sapi', 'price' => 5000, 'is_default' => false],
                                ['name' => 'Sosis Sapi', 'price' => 6000, 'is_default' => false],
                                ['name' => 'Kerupuk Udang', 'price' => 3000, 'is_default' => false],
                            ],
                        ],
                    ],
                ],
                [
                    'sku' => 'PRD-MIEAYAM-02',
                    'barcode' => '899100100002',
                    'name' => 'Mie Ayam Bakso',
                    'category' => 'Makanan Utama',
                    'unit' => 'Porsi',
                    'initial_price' => 12000,
                    'selling_price' => 22000,
                    'stock' => 80,
                    'variations' => [
                        [
                            'name' => 'Kuah',
                            'type' => 'single',
                            'is_required' => true,
                            'options' => [
                                ['name' => 'Kuah Pisah', 'price' => 0, 'is_default' => true],
                                ['name' => 'Kuah Campur', 'price' => 0, 'is_default' => false],
                            ],
                        ],
                    ],
                ],
                [
                    'sku' => 'PRD-KOPISUSU-03',
                    'barcode' => '899100100003',
                    'name' => 'Kopi Susu Gula Aren',
                    'category' => 'Minuman Kopi & Teh',
                    'unit' => 'Cup',
                    'initial_price' => 9000,
                    'selling_price' => 18000,
                    'stock' => 120,
                    'variations' => [
                        [
                            'name' => 'Suhu',
                            'type' => 'single',
                            'is_required' => true,
                            'options' => [
                                ['name' => 'Dingin (Ice)', 'price' => 0, 'is_default' => true],
                                ['name' => 'Panas (Hot)', 'price' => 0, 'is_default' => false],
                            ],
                        ],
                        [
                            'name' => 'Takaran Gula',
                            'type' => 'single',
                            'is_required' => true,
                            'options' => [
                                ['name' => 'Normal Sugar', 'price' => 0, 'is_default' => true],
                                ['name' => 'Less Sugar', 'price' => 0, 'is_default' => false],
                            ],
                        ],
                    ],
                ],
                [
                    'sku' => 'PRD-ESTEH-04',
                    'barcode' => '899100100004',
                    'name' => 'Es Teh Manis Jumbo',
                    'category' => 'Minuman Kopi & Teh',
                    'unit' => 'Cup',
                    'initial_price' => 3000,
                    'selling_price' => 8000,
                    'stock' => 150,
                    'variations' => [],
                ],
                [
                    'sku' => 'PRD-KENTANG-05',
                    'barcode' => '899100100005',
                    'name' => 'Kentang Goreng Crispy',
                    'category' => 'Snack & Side Dish',
                    'unit' => 'Porsi',
                    'initial_price' => 8000,
                    'selling_price' => 15000,
                    'stock' => 90,
                    'variations' => [],
                ],
                [
                    'sku' => 'PRD-PISANG-06',
                    'barcode' => '899100100006',
                    'name' => 'Pisang Bakar Coklat Keju',
                    'category' => 'Dessert',
                    'unit' => 'Porsi',
                    'initial_price' => 9000,
                    'selling_price' => 17000,
                    'stock' => 70,
                    'variations' => [],
                ],
            ];

            $seededProducts = [];

            foreach ($productsCatalog as $item) {
                $product = Product::updateOrCreate(
                    ['sku' => $item['sku']],
                    [
                        'category_id' => $categories[$item['category']]->id,
                        'name' => $item['name'],
                        'type' => 'product',
                        'unit' => $item['unit'],
                        'initial_price' => $item['initial_price'],
                        'selling_price' => $item['selling_price'],
                        'stock' => $item['stock'],
                        'is_non_stock' => false,
                        'show' => true,
                    ]
                );

                Barcode::firstOrCreate(
                    ['product_id' => $product->id, 'code' => $item['barcode']],
                    ['type' => 'primary', 'is_active' => true]
                );

                if ($product->stocks()->count() === 0) {
                    Stock::create([
                        'product_id' => $product->id,
                        'stock' => $item['stock'],
                        'init_stock' => $item['stock'],
                        'initial_price' => $item['initial_price'],
                        'selling_price' => $item['selling_price'],
                        'type' => 'in',
                        'is_ready' => 1,
                        'date' => today(),
                    ]);
                }

                foreach ($item['variations'] as $vIndex => $varData) {
                    $variation = ProductVariation::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'name' => $varData['name'],
                        ],
                        [
                            'type' => $varData['type'],
                            'is_required' => $varData['is_required'],
                            'sort_order' => $vIndex,
                        ]
                    );

                    foreach ($varData['options'] as $oIndex => $optData) {
                        ProductVariationOption::updateOrCreate(
                            [
                                'product_variation_id' => $variation->id,
                                'name' => $optData['name'],
                            ],
                            [
                                'price' => $optData['price'],
                                'is_default' => $optData['is_default'],
                                'sort_order' => $oIndex,
                            ]
                        );
                    }
                }

                $product->load('variations.options');
                $seededProducts[$item['sku']] = $product;
            }

            // 4. Seed Sample Cart Draft
            $nasgor = $seededProducts['PRD-NASGOR-01'];
            $kopi = $seededProducts['PRD-KOPISUSU-03'];

            CartDraft::updateOrCreate(
                ['name' => 'Pelanggan Meja 02 - Pak Andi'],
                [
                    'user_id' => $user->id,
                    'order_type' => 'dine_in',
                    'member_id' => $member->id,
                    'table_id' => $tables['Meja 02']->id,
                    'discount_price' => 0,
                    'note' => 'Tunggu rekan datang 10 menit lagi',
                    'total_price' => 43000,
                    'items' => [
                        [
                            'product_id' => $nasgor->id,
                            'qty' => 1,
                            'price' => 25000,
                            'discount_price' => 0,
                            'price_unit_id' => null,
                            'selected_variations' => [
                                [
                                    'variation_id' => $nasgor->variations[0]->id,
                                    'variation_name' => $nasgor->variations[0]->name,
                                    'option_id' => $nasgor->variations[0]->options[1]->id,
                                    'option_name' => $nasgor->variations[0]->options[1]->name,
                                    'price' => 0,
                                ],
                            ],
                            'note' => 'Jangan pakai daun bawang',
                        ],
                        [
                            'product_id' => $kopi->id,
                            'qty' => 1,
                            'price' => 18000,
                            'discount_price' => 0,
                            'price_unit_id' => null,
                            'selected_variations' => [
                                [
                                    'variation_id' => $kopi->variations[0]->id,
                                    'variation_name' => $kopi->variations[0]->name,
                                    'option_id' => $kopi->variations[0]->options[0]->id,
                                    'option_name' => $kopi->variations[0]->options[0]->name,
                                    'price' => 0,
                                ],
                                [
                                    'variation_id' => $kopi->variations[1]->id,
                                    'variation_name' => $kopi->variations[1]->name,
                                    'option_id' => $kopi->variations[1]->options[1]->id,
                                    'option_name' => $kopi->variations[1]->options[1]->name,
                                    'price' => 0,
                                ],
                            ],
                            'note' => 'Es batu sedikit saja',
                        ],
                    ],
                ]
            );

            // 5. Seed Selling History (3 transaksi lengkap untuk keperluan Report & History)
            $historyTransactions = [
                [
                    'code' => 'TRX-PERSONAL-001',
                    'date' => now()->subDay(),
                    'order_type' => 'dine_in',
                    'table_id' => $tables['Meja 01']->id,
                    'member_id' => $member->id,
                    'payment_method_id' => $cashMethod->id,
                    'note' => 'Transaksi makan siang keluarga',
                    'payed_money' => 100000,
                    'items' => [
                        [
                            'product' => $seededProducts['PRD-NASGOR-01'],
                            'qty' => 1,
                            'unit_price' => 25000,
                            'discount_price' => 0,
                            'note' => 'Pedas sedang, tanpa acar',
                            'selected_variations' => [
                                [
                                    'variation_id' => $nasgor->variations[0]->id,
                                    'variation_name' => 'Level Pedas',
                                    'option_id' => $nasgor->variations[0]->options[1]->id,
                                    'option_name' => 'Sedang',
                                    'price' => 0,
                                ],
                            ],
                        ],
                        [
                            'product' => $seededProducts['PRD-NASGOR-01'],
                            'qty' => 1,
                            'unit_price' => 32000,
                            'discount_price' => 0,
                            'note' => 'Ekstra pedas + telur matang',
                            'selected_variations' => [
                                [
                                    'variation_id' => $nasgor->variations[0]->id,
                                    'variation_name' => 'Level Pedas',
                                    'option_id' => $nasgor->variations[0]->options[2]->id,
                                    'option_name' => 'Pedas Ekstra',
                                    'price' => 2000,
                                ],
                                [
                                    'variation_id' => $nasgor->variations[1]->id,
                                    'variation_name' => 'Topping Tambahan',
                                    'option_id' => $nasgor->variations[1]->options[0]->id,
                                    'option_name' => 'Telur Mata Sapi',
                                    'price' => 5000,
                                ],
                            ],
                        ],
                        [
                            'product' => $seededProducts['PRD-ESTEH-04'],
                            'qty' => 2,
                            'unit_price' => 8000,
                            'discount_price' => 0,
                            'note' => '1 es normal, 1 es sedikit',
                            'selected_variations' => null,
                        ],
                    ],
                ],
                [
                    'code' => 'TRX-PERSONAL-002',
                    'date' => now()->subHours(3),
                    'order_type' => 'take_away',
                    'table_id' => null,
                    'member_id' => null,
                    'payment_method_id' => $qrisMethod->id,
                    'note' => 'Pesanan bungkus bawa pulang',
                    'payed_money' => 53000,
                    'items' => [
                        [
                            'product' => $seededProducts['PRD-KOPISUSU-03'],
                            'qty' => 2,
                            'unit_price' => 18000,
                            'discount_price' => 0,
                            'note' => 'Cup dipisah kantong',
                            'selected_variations' => [
                                [
                                    'variation_id' => $kopi->variations[0]->id,
                                    'variation_name' => 'Suhu',
                                    'option_id' => $kopi->variations[0]->options[0]->id,
                                    'option_name' => 'Dingin (Ice)',
                                    'price' => 0,
                                ],
                                [
                                    'variation_id' => $kopi->variations[1]->id,
                                    'variation_name' => 'Takaran Gula',
                                    'option_id' => $kopi->variations[1]->options[0]->id,
                                    'option_name' => 'Normal Sugar',
                                    'price' => 0,
                                ],
                            ],
                        ],
                        [
                            'product' => $seededProducts['PRD-PISANG-06'],
                            'qty' => 1,
                            'unit_price' => 17000,
                            'discount_price' => 0,
                            'note' => 'Keju diperbanyak',
                            'selected_variations' => null,
                        ],
                    ],
                ],
                [
                    'code' => 'TRX-PERSONAL-003',
                    'date' => now()->subHour(),
                    'order_type' => 'ojek_online',
                    'table_id' => null,
                    'member_id' => null,
                    'payment_method_id' => $qrisMethod->id,
                    'note' => 'Order GoFood #GF-8821',
                    'payed_money' => 37000,
                    'items' => [
                        [
                            'product' => $seededProducts['PRD-MIEAYAM-02'],
                            'qty' => 1,
                            'unit_price' => 22000,
                            'discount_price' => 0,
                            'note' => 'Sambal bungkus terpisah',
                            'selected_variations' => null,
                        ],
                        [
                            'product' => $seededProducts['PRD-KENTANG-05'],
                            'qty' => 1,
                            'unit_price' => 15000,
                            'discount_price' => 0,
                            'note' => 'Saus tomat & sambal',
                            'selected_variations' => null,
                        ],
                    ],
                ],
            ];

            foreach ($historyTransactions as $trx) {
                $totalPrice = 0;
                $totalCost = 0;
                $totalQty = 0;
                $totalDiscountPerItem = 0;

                foreach ($trx['items'] as $line) {
                    $linePrice = $line['unit_price'] * $line['qty'];
                    $lineCost = $line['product']->initial_price * $line['qty'];
                    $totalPrice += $linePrice;
                    $totalCost += $lineCost;
                    $totalQty += $line['qty'];
                    $totalDiscountPerItem += ($line['discount_price'] ?? 0);
                }

                $selling = Selling::updateOrCreate(
                    ['code' => $trx['code']],
                    [
                        'user_id' => $user->id,
                        'member_id' => $trx['member_id'],
                        'table_id' => $trx['table_id'],
                        'order_type' => $trx['order_type'],
                        'payment_method_id' => $trx['payment_method_id'],
                        'date' => $trx['date']->format('Y-m-d H:i:s'),
                        'payed_money' => $trx['payed_money'],
                        'money_changes' => $trx['payed_money'] - ($totalPrice - $totalDiscountPerItem),
                        'total_price' => $totalPrice,
                        'total_cost' => $totalCost,
                        'total_qty' => $totalQty,
                        'tax' => 0,
                        'tax_price' => 0,
                        'discount_price' => 0,
                        'total_discount_per_item' => $totalDiscountPerItem,
                        'note' => $trx['note'],
                        'is_paid' => true,
                    ]
                );

                $selling->sellingDetails()->delete();

                foreach ($trx['items'] as $line) {
                    SellingDetail::create([
                        'selling_id' => $selling->id,
                        'product_id' => $line['product']->id,
                        'qty' => $line['qty'],
                        'price' => $line['unit_price'] * $line['qty'],
                        'cost' => $line['product']->initial_price * $line['qty'],
                        'discount_price' => $line['discount_price'] ?? 0,
                        'selected_variations' => $line['selected_variations'],
                        'note' => $line['note'],
                    ]);
                }
            }
        });

        // 6. Verifikasi Report Services (Selling Report, Product Report, Cashier Report)
        $reportFilter = [
            'start_date' => now()->subDays(7)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        $sellingReport = app(SellingReportService::class)->generate($reportFilter);
        $productReport = app(ProductReportService::class)->generate($reportFilter);
        $cashierReport = app(CashierReportService::class)->generate($reportFilter);

        if ($this->command) {
            $this->command->info('=== PersonalPosSeeder Berhasil Dijalankan ===');
            $this->command->info('- Categories : '.Category::count().' kategori');
            $this->command->info('- Tables     : '.Table::count().' meja');
            $this->command->info('- Products   : '.Product::count().' produk (+ variasi & stok)');
            $this->command->info('- Draft POS  : '.CartDraft::count().' draft pesanan');
            $this->command->info('- Sellings   : '.Selling::count().' transaksi penjualan');
            $this->command->info('- PIN Toko   : Default 123456 (Aktif)');
            $this->command->info('=== Hasil Pengecekan Modul Report ===');
            $this->command->info('- Selling Report Rows : '.count($sellingReport['reports']).' baris | Total Net: '.$sellingReport['footer']['total_net']);
            $this->command->info('- Product Report Rows : '.count($productReport['reports']).' produk terjual | Total Qty: '.$productReport['footer']['total_qty']);
            $this->command->info('- Cashier Report Rows : '.count($cashierReport['reports']).' transaksi kasir');
        }
    }
}
