<?php

namespace Database\Seeders;

use App\Models\Tenants\Category;
use App\Models\Tenants\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class RestaurantProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = $this->getRestaurantProducts();

        $storageDir = storage_path('app/public/product');
        if (! File::isDirectory($storageDir)) {
            File::makeDirectory($storageDir, 0755, true);
        }

        foreach ($products as $item) {
            // 1. Get or create category
            $category = Category::firstOrCreate(['name' => $item['category']]);

            // 2. Download image or save fallback
            $fileName = $item['slug'] . '.jpg';
            $relativeStoragePath = 'product/' . $fileName;
            $fullFilePath = $storageDir . '/' . $fileName;

            if (! File::exists($fullFilePath)) {
                try {
                    $response = Http::timeout(15)->get($item['image_url']);
                    if ($response->successful()) {
                        File::put($fullFilePath, $response->body());
                    } else {
                        // Fallback: copy a 1x1 dummy or keep url
                        $relativeStoragePath = $item['image_url'];
                    }
                } catch (\Throwable $e) {
                    $relativeStoragePath = $item['image_url'];
                }
            }

            // 3. Create or update product
            $product = Product::updateOrCreate(
                ['name' => $item['name']],
                [
                    'category_id' => $category->id,
                    'stock' => $item['stock'],
                    'initial_price' => $item['initial_price'],
                    'selling_price' => $item['selling_price'],
                    'unit' => $item['unit'],
                    'type' => 'product',
                    'show' => true,
                    'is_non_stock' => false,
                    'hero_images' => $relativeStoragePath,
                ]
            );
        }
    }

    private function getRestaurantProducts(): array
    {
        return [
            // 1. Makanan Utama
            [
                'category' => 'Makanan Utama',
                'name' => 'Nasi Goreng Spesial',
                'slug' => 'nasi-goreng-spesial',
                'stock' => 50,
                'initial_price' => 15000,
                'selling_price' => 28000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Makanan Utama',
                'name' => 'Mie Goreng Seafood',
                'slug' => 'mie-goreng-seafood',
                'stock' => 40,
                'initial_price' => 18000,
                'selling_price' => 32000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1585032226651-759b368d7246?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Makanan Utama',
                'name' => 'Ayam Bakar Madu',
                'slug' => 'ayam-bakar-madu',
                'stock' => 35,
                'initial_price' => 18000,
                'selling_price' => 30000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Makanan Utama',
                'name' => 'Ayam Goreng Kremes',
                'slug' => 'ayam-goreng-kremes',
                'stock' => 45,
                'initial_price' => 16000,
                'selling_price' => 28000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Makanan Utama',
                'name' => 'Bebek Goreng Sambal Korek',
                'slug' => 'bebek-goreng-sambal-korek',
                'stock' => 25,
                'initial_price' => 24000,
                'selling_price' => 42000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1514944298352-fa0d09993952?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Makanan Utama',
                'name' => 'Sate Ayam Madura',
                'slug' => 'sate-ayam-madura',
                'stock' => 50,
                'initial_price' => 16000,
                'selling_price' => 28000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Makanan Utama',
                'name' => 'Sop Buntut Sapi',
                'slug' => 'sop-buntut-sapi',
                'stock' => 20,
                'initial_price' => 35000,
                'selling_price' => 58000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1547928576-a4a33237cbc3?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Makanan Utama',
                'name' => 'Ikan Gurame Asam Manis',
                'slug' => 'ikan-gurame-asam-manis',
                'stock' => 20,
                'initial_price' => 32000,
                'selling_price' => 55000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Makanan Utama',
                'name' => 'Kwetiau Goreng Sapi',
                'slug' => 'kwetiau-goreng-sapi',
                'stock' => 30,
                'initial_price' => 20000,
                'selling_price' => 35000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1612927601601-6638404737ce?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Makanan Utama',
                'name' => 'Capcay Seafood',
                'slug' => 'capcay-seafood',
                'stock' => 30,
                'initial_price' => 16000,
                'selling_price' => 28000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=600&auto=format&fit=crop&q=80',
            ],

            // 2. Camilan & Pembuka
            [
                'category' => 'Camilan & Pembuka',
                'name' => 'Kentang Goreng',
                'slug' => 'kentang-goreng',
                'stock' => 60,
                'initial_price' => 8000,
                'selling_price' => 18000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1576107232684-1279f3908594?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Camilan & Pembuka',
                'name' => 'Tahu Crispy Cabe Garam',
                'slug' => 'tahu-crispy-cabe-garam',
                'stock' => 40,
                'initial_price' => 7000,
                'selling_price' => 16000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Camilan & Pembuka',
                'name' => 'Dimsum Ayam Udang',
                'slug' => 'dimsum-ayam-udang',
                'stock' => 50,
                'initial_price' => 12000,
                'selling_price' => 22000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Camilan & Pembuka',
                'name' => 'Roti Bakar Kaya Butter',
                'slug' => 'roti-bakar-kaya-butter',
                'stock' => 35,
                'initial_price' => 9000,
                'selling_price' => 18000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1525351484163-7529414344d8?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Camilan & Pembuka',
                'name' => 'Pisang Goreng Keju Coklat',
                'slug' => 'pisang-goreng-keju-coklat',
                'stock' => 40,
                'initial_price' => 9000,
                'selling_price' => 18000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1528975604071-b4dc52a2d18c?w=600&auto=format&fit=crop&q=80',
            ],

            // 3. Minuman
            [
                'category' => 'Minuman',
                'name' => 'Es Teh Manis',
                'slug' => 'es-teh-manis',
                'stock' => 100,
                'initial_price' => 2000,
                'selling_price' => 6000,
                'unit' => 'Gelas',
                'image_url' => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Minuman',
                'name' => 'Es Jeruk Peras Segar',
                'slug' => 'es-jeruk-peras-segar',
                'stock' => 80,
                'initial_price' => 5000,
                'selling_price' => 12000,
                'unit' => 'Gelas',
                'image_url' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Minuman',
                'name' => 'Kopi Susu Gula Aren',
                'slug' => 'kopi-susu-gula-aren',
                'stock' => 75,
                'initial_price' => 8000,
                'selling_price' => 20000,
                'unit' => 'Cup',
                'image_url' => 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Minuman',
                'name' => 'Matcha Latte',
                'slug' => 'matcha-latte',
                'stock' => 50,
                'initial_price' => 10000,
                'selling_price' => 24000,
                'unit' => 'Cup',
                'image_url' => 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Minuman',
                'name' => 'Jus Alpukat Coklat',
                'slug' => 'jus-alpukat-coklat',
                'stock' => 40,
                'initial_price' => 9000,
                'selling_price' => 18000,
                'unit' => 'Gelas',
                'image_url' => 'https://images.unsplash.com/photo-1525385133512-2f3bdd039054?w=600&auto=format&fit=crop&q=80',
            ],

            // 4. Penutup
            [
                'category' => 'Penutup',
                'name' => 'Es Campur Lakasir',
                'slug' => 'es-campur-lakasir',
                'stock' => 35,
                'initial_price' => 8000,
                'selling_price' => 18000,
                'unit' => 'Mangkuk',
                'image_url' => 'https://images.unsplash.com/photo-1501443762994-82bd5dace89a?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'category' => 'Penutup',
                'name' => 'Mango Sticky Rice',
                'slug' => 'mango-sticky-rice',
                'stock' => 30,
                'initial_price' => 12000,
                'selling_price' => 25000,
                'unit' => 'Porsi',
                'image_url' => 'https://images.unsplash.com/photo-1563805042-7684c019e1cb?w=600&auto=format&fit=crop&q=80',
            ],
        ];
    }
}

