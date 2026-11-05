<?php

namespace App\Filament\Tenant\Resources\ProductResource\Pages;

use App\Filament\Tenant\Resources\ProductResource;
use App\Filament\Tenant\Resources\Traits\RedirectToIndex;
use App\Models\Tenants\Product;
use App\Models\Tenants\UploadedFile;
use App\Services\Tenants\ProductService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditProduct extends EditRecord
{
    use RedirectToIndex;

    protected static string $resource = ProductResource::class;

    private ProductService $productService;

    public function __construct()
    {
        $this->productService = new ProductService();
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
            ->url(static::getResource()::getUrl())
            ->color('gray');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->getRecord()->attributesToArray();

        $heroImages = $data['hero_images'] ?? [];
        $uploadedFile = UploadedFile::where(function ($q) use ($heroImages) {
            $q->whereIn('relative_path', $heroImages)->orWhereIn('url', $heroImages);
        })->select(['name', 'original_name', 'relative_path', 'url'])
            ->get();

        $uploadedFile->each(function ($file, $key) use (&$data) {
            $data['hero_images'][$key] = $file->relative_path;
            $data['original_name'][$file->relative_path] = $file->original_name;
        });

        $data['barcodes'] = $this->getRecord()->barcodes()->get()->map(function ($barcode) {
            return [
                'id' => $barcode->id,
                'code' => $barcode->code,
                'type' => $barcode->type,
                'description' => $barcode->description,
            ];
        })->toArray();

        $data['variations'] = $this->getRecord()->variations()->with('options')->get()->map(function ($variation) {
            return [
                'id' => $variation->id,
                'name' => $variation->name,
                'type' => $variation->type,
                'is_required' => (bool) $variation->is_required,
                'sort_order' => $variation->sort_order,
                'options' => $variation->options->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'name' => $option->name,
                        'price' => $option->price,
                        'is_default' => (bool) $option->is_default,
                        'sort_order' => $option->sort_order,
                    ];
                })->toArray(),
            ];
        })->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $barcodes = $data['barcodes'] ?? [];
        unset($data['barcodes']);
        unset($data['variations']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Product $product */
        $product = $this->getRecord();

        $newHeroImages = $data['hero_images'] ?? [];

        if (! $product->hero_images) {
            $product->hero_images = collect([]);
        }

        $deletedHeroImages = collect($product->hero_images)->diff($newHeroImages);

        $this->productService->handleDeleteUploadedFile($deletedHeroImages->toArray());

        $model = parent::handleRecordUpdate($record, $data);

        return $model;
    }

    public function afterSave()
    {
        /** @var Product $product */
        $product = $this->record;
        $product->hero_images = $this->productService
            ->handleCreateUploadedFile(
                $this->form->getState()['original_name']
            );

        $product->save();

        $barcodesData = $this->data['barcodes'] ?? [];

        $product->barcodes()->delete();

        foreach ($barcodesData as $barcodeData) {
            if (!empty($barcodeData['code'])) {
                $product->barcodes()->create([
                    'code' => $barcodeData['code'],
                    'type' => $barcodeData['type'] ?? 'primary',
                    'description' => $barcodeData['description'] ?? null,
                    'is_active' => true,
                ]);
            }
        }

        $variationsData = $this->data['variations'] ?? [];

        $product->variations()->delete();

        foreach ($variationsData as $varIndex => $varData) {
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
