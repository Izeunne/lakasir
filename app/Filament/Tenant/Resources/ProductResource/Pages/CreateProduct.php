<?php

namespace App\Filament\Tenant\Resources\ProductResource\Pages;

use App\Filament\Tenant\Resources\ProductResource;
use App\Filament\Tenant\Resources\Traits\RedirectToIndex;
use App\Models\Tenants\Barcode;
use App\Models\Tenants\Product;
use App\Observers\ProductObserver;
use App\Services\Tenants\ProductService;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use RedirectToIndex;

    protected static string $resource = ProductResource::class;

    private ProductService $productService;
    private array $barcodesData = [];
    private array $variationsData = [];

    public function __construct()
    {
        $this->productService = new ProductService();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->barcodesData = $data['barcodes'] ?? [];

        if (!empty($this->barcodesData)) {
            ProductObserver::setTempBarcodesData($this->barcodesData);
        }
        unset($data['barcodes']);

        $this->variationsData = $data['variations'] ?? [];
        unset($data['variations']);

        return $data;
    }

    public function afterCreate()
    {
        /** @var Product $product */
        $product = $this->record;

        $product->hero_images = $this
            ->productService
            ->handleCreateUploadedFile(
                $this->form->getState()['original_name']
            );
        $product->save();

        $stock = $product->stocks->first();
        $stock->expired = $this->data['expired'] ?? null;
        $stock->save();

        if (!empty($this->barcodesData)) {
            foreach ($this->barcodesData as $barcodeData) {
                if (!empty($barcodeData['code'])) {
                    $product->barcodes()->create([
                        'code' => $barcodeData['code'],
                        'type' => $barcodeData['type'] ?? 'primary',
                        'description' => $barcodeData['description'] ?? null,
                        'is_active' => true,
                    ]);
                }
            }
        }

        if (!empty($this->variationsData)) {
            foreach ($this->variationsData as $varIndex => $varData) {
                if (!empty($varData['name'])) {
                    $variation = $product->variations()->create([
                        'name' => $varData['name'],
                        'type' => $varData['type'] ?? 'single',
                        'is_required' => $varData['is_required'] ?? false,
                        'sort_order' => $varIndex,
                    ]);

                    if (!empty($varData['options'])) {
                        foreach ($varData['options'] as $optIndex => $optData) {
                            if (!empty($optData['name'])) {
                                $variation->options()->create([
                                    'name' => $optData['name'],
                                    'price' => (float) ($optData['price'] ?? 0),
                                    'is_default' => $optData['is_default'] ?? false,
                                    'sort_order' => $optIndex,
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }
}
