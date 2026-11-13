<?php

namespace Database\Seeders;

use App\Models\Tenants\About;
use App\Models\Tenants\Category;
use App\Models\Tenants\Member;
use App\Models\Tenants\PaymentMethod;
use App\Models\Tenants\Printer;
use App\Models\Tenants\Product;
use App\Models\Tenants\Selling;
use App\Models\Tenants\SellingDetail;
use App\Models\Tenants\Stock;
use App\Models\Tenants\Table;
use App\Models\Tenants\User;
use App\Services\PrintBridgeService;
use Illuminate\Database\Seeder;

class DemoRestaurantSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan User Kasir memiliki nama
        $user = User::first();
        if ($user && empty($user->name)) {
            $user->update(['name' => 'Hermanto (Kasir)']);
        }

        // 2. Pastikan About / Identitas Toko terisi lengkap
        $about = About::firstOrCreate(['id' => 1], [
            'currency' => 'IDR',
            'business_type' => 'fnb',
        ]);

        $about->update([
            'shop_name' => 'Waroeng Beribu Rasa Renon',
            'shop_location' => 'Jalan Pasekan, Batubulan Kangin, Sukawati, Bali',
            'phone' => '0812-3456-7890',
            'email' => 'info@waroengberiburasa.com',
            'website' => 'https://waroengberiburasa.com',
            'npwp' => '01.234.567.8-901.000',
            'business_type' => 'fnb',
        ]);

        // 3. Pastikan Payment Method
        $paymentCash = PaymentMethod::firstOrCreate(
            ['name' => 'Tunai (Cash)'],
            ['is_cash' => true]
        );

        PaymentMethod::firstOrCreate(
            ['name' => 'QRIS'],
            ['is_cash' => false]
        );

        // 4. Pastikan Table (Meja)
        $table05 = Table::firstOrCreate(
            ['number' => '05']
        );

        // 5. Pastikan Member / Pelanggan
        $member = Member::firstOrCreate(
            ['name' => 'Bpk. Wayan Darmawan'],
            ['email' => 'wayan@example.com', 'code' => 'MBR-001']
        );

        // 6. Buat Kategori Menu
        $catMakanan = Category::firstOrCreate(['name' => 'Makanan Utama']);
        $catCamilan = Category::firstOrCreate(['name' => 'Camilan & Penutup']);
        $catMinuman = Category::firstOrCreate(['name' => 'Minuman']);

        // 7. Buat Produk / Menu Makanan Utama
        $items = [
            [
                'category_id' => $catMakanan->id,
                'name' => 'Nasi Goreng Spesial Renon',
                'selling_price' => 28000,
                'initial_price' => 15000,
                'stock' => 100,
            ],
            [
                'category_id' => $catMakanan->id,
                'name' => 'Ayam Bakar Madu Pedas Manis',
                'selling_price' => 32000,
                'initial_price' => 18000,
                'stock' => 80,
            ],
            [
                'category_id' => $catMakanan->id,
                'name' => 'Bebek Crispy Sambal Matah',
                'selling_price' => 38000,
                'initial_price' => 22000,
                'stock' => 50,
            ],
            [
                'category_id' => $catMakanan->id,
                'name' => 'Mie Goreng Seafood Spesial',
                'selling_price' => 30000,
                'initial_price' => 17000,
                'stock' => 70,
            ],
            // Camilan & Penutup
            [
                'category_id' => $catCamilan->id,
                'name' => 'Cireng Crispy Bumbu Rujak',
                'selling_price' => 15000,
                'initial_price' => 7000,
                'stock' => 60,
            ],
            [
                'category_id' => $catCamilan->id,
                'name' => 'Kentang Goreng French Fries',
                'selling_price' => 18000,
                'initial_price' => 9000,
                'stock' => 60,
            ],
            [
                'category_id' => $catCamilan->id,
                'name' => 'Pisang Goreng Keju Gula Aren',
                'selling_price' => 16000,
                'initial_price' => 8000,
                'stock' => 50,
            ],
            // Minuman
            [
                'category_id' => $catMinuman->id,
                'name' => 'Es Teh Manis Melati',
                'selling_price' => 6000,
                'initial_price' => 2000,
                'stock' => 200,
            ],
            [
                'category_id' => $catMinuman->id,
                'name' => 'Kopi Susu Gula Aren Renon',
                'selling_price' => 18000,
                'initial_price' => 8000,
                'stock' => 100,
            ],
            [
                'category_id' => $catMinuman->id,
                'name' => 'Es Jeruk Peras Murni',
                'selling_price' => 8000,
                'initial_price' => 3500,
                'stock' => 120,
            ],
            [
                'category_id' => $catMinuman->id,
                'name' => 'Jus Alpukat Kocok Coklat',
                'selling_price' => 20000,
                'initial_price' => 10000,
                'stock' => 80,
            ],
        ];

        $productModels = [];
        foreach ($items as $itemData) {
            $product = Product::updateOrCreate(
                ['name' => $itemData['name']],
                [
                    'category_id' => $itemData['category_id'],
                    'selling_price' => $itemData['selling_price'],
                    'initial_price' => $itemData['initial_price'],
                    'stock' => $itemData['stock'],
                    'unit' => 'Porsi',
                    'type' => 'product',
                    'is_non_stock' => false,
                    'show' => true,
                ]
            );

            // Pastikan stok masuk tersedia
            Stock::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'type' => 'in',
                    'is_ready' => true,
                ],
                [
                    'stock' => $itemData['stock'],
                    'init_stock' => $itemData['stock'],
                    'initial_price' => $itemData['initial_price'],
                    'selling_price' => $itemData['selling_price'],
                    'date' => now()->toDateString(),
                ]
            );

            $productModels[$itemData['name']] = $product;
        }

        // 8. Setup 3 Stasiun Multi-Printer Standar (Real POS)
        // Stasiun 1: Printer Kasir (Pelanggan & Salinan Toko) -> copies = 2, show_price = true, show_header = true
        Printer::updateOrCreate(
            ['name' => 'Printer Kasir (Tagihan & Salinan)'],
            [
                'driver' => 'bluetooth',
                'connection_type' => 'bluetooth',
                'device_id' => 'BT-KASIR-01',
                'device_name' => 'Thermal Kasir Bluetooth',
                'paper_width' => '58mm',
                'print_all_categories' => true,
                'category_ids' => [],
                'show_price' => true,
                'show_header' => true,
                'copies' => 2, // 1 untuk Pelanggan, 1 untuk Salinan Toko / Disimpan
                'header' => 'Struk Pembayaran Kasir',
                'footer' => 'Terima Kasih Atas Kunjungan Anda! Harap simpan struk ini sebagai bukti pembayaran sah.',
                'is_active' => true,
            ]
        );

        // Stasiun 2: Printer Dapur (Khusus Makanan & Camilan) -> show_price = false (Tanpa Harga), show_header = false
        Printer::updateOrCreate(
            ['name' => 'Printer Dapur (Checker Makanan)'],
            [
                'driver' => 'lan',
                'connection_type' => 'lan',
                'device_id' => '192.168.1.201:9100',
                'device_name' => 'Epson TM-U220 Dapur',
                'paper_width' => '80mm',
                'print_all_categories' => false,
                'category_ids' => [$catMakanan->id, $catCamilan->id],
                'show_price' => false, // Nota Checker Dapur: Tanpa Harga
                'show_header' => false,
                'copies' => 1,
                'header' => '*** PESANAN DAPUR (MAKANAN) ***',
                'footer' => '--- Segera Siapkan Pesanan ---',
                'is_active' => true,
            ]
        );

        // Stasiun 3: Printer Bar (Khusus Minuman) -> show_price = false (Tanpa Harga), show_header = false
        Printer::updateOrCreate(
            ['name' => 'Printer Bar (Checker Minuman)'],
            [
                'driver' => 'lan',
                'connection_type' => 'lan',
                'device_id' => '192.168.1.202:9100',
                'device_name' => 'Epson TM-T82 Bar',
                'paper_width' => '58mm',
                'print_all_categories' => false,
                'category_ids' => [$catMinuman->id],
                'show_price' => false, // Nota Checker Bar: Tanpa Harga
                'show_header' => false,
                'copies' => 1,
                'header' => '*** PESANAN BAR (MINUMAN) ***',
                'footer' => '--- Segera Racik Minuman ---',
                'is_active' => true,
            ]
        );

        // 9. Simulasikan Transaksi Penjualan Lengkap (Dine In Meja 05)
        $selling = Selling::create([
            'user_id' => $user->id,
            'table_id' => $table05->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentCash->id,
            'code' => 'TRX-' . now()->format('Ymd') . '-0001',
            'date' => now()->toDateTimeString(),
            'order_type' => 'dine_in',
            'total_price' => 133000,
            'total_cost' => 69000,
            'total_qty' => 7,
            'discount_price' => 0,
            'tax' => 0,
            'tax_price' => 0,
            'payed_money' => 150000,
            'money_changes' => 17000,
            'is_paid' => true,
        ]);

        // Detail Transaksi
        $detailsData = [
            [
                'product' => $productModels['Nasi Goreng Spesial Renon'],
                'qty' => 2,
                'price' => 28000,
                'cost' => 15000,
                'note' => 'Pedas sedang, telur mata sapi matang',
            ],
            [
                'product' => $productModels['Ayam Bakar Madu Pedas Manis'],
                'qty' => 1,
                'price' => 32000,
                'cost' => 18000,
                'note' => 'Sambal dipisah',
            ],
            [
                'product' => $productModels['Cireng Crispy Bumbu Rujak'],
                'qty' => 1,
                'price' => 15000,
                'cost' => 7000,
                'note' => 'Goreng garing',
            ],
            [
                'product' => $productModels['Es Teh Manis Melati'],
                'qty' => 2,
                'price' => 6000,
                'cost' => 2000,
                'note' => 'Less sugar, es banyak',
            ],
            [
                'product' => $productModels['Kopi Susu Gula Aren Renon'],
                'qty' => 1,
                'price' => 18000,
                'cost' => 8000,
                'note' => 'Normal ice & sugar',
            ],
        ];

        foreach ($detailsData as $d) {
            SellingDetail::create([
                'selling_id' => $selling->id,
                'product_id' => $d['product']->id,
                'qty' => $d['qty'],
                'price' => $d['price'],
                'cost' => $d['cost'],
                'note' => $d['note'],
            ]);
        }

        // 10. Otomatis kirim ke Print Queue Multi-Printer
        PrintBridgeService::queueSellingPrints($selling);
    }
}
