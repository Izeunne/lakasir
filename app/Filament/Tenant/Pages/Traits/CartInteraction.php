<?php

namespace App\Filament\Tenant\Pages\Traits;

use App\Models\Tenants\CartDraft;
use App\Models\Tenants\CartItem;
use App\Models\Tenants\Product;
use App\Models\Tenants\Table;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

trait CartInteraction
{
    private function validateStock(Product $product, $qty): bool
    {
        $available = $product->stock_calculate;
        if (! $product->is_non_stock && ($available < 0 || $available < $qty)) {
            Notification::make()
                ->title(__('Stock is out'))
                ->danger()
                ->send();
            $this->mount();

            return false;
        }

        return true;
    }

    public ?Product $selectedProductForVariation = null;
    public array $selectedVariationOptions = [];
    public int $variationQty = 1;
    public ?int $expandedVariationCartItemId = null;

    public function addVariationItem(int $productId): void
    {
        $product = Product::with('variations.options')->find($productId);
        if (! $product) {
            return;
        }

        $this->addCart($product);
    }

    public function handleProductSelect(Product $product)
    {
        $product->loadMissing('variations.options');

        if ($product->variations->isNotEmpty()) {
            $this->openVariationModal($product);
        } else {
            $this->addCart($product);
        }
    }

    public function openVariationModal(Product $product)
    {
        $product->loadMissing('variations.options');
        $this->selectedProductForVariation = $product;
        $this->variationQty = 1;
        $this->selectedVariationOptions = [];

        foreach ($product->variations as $var) {
            if ($var->type === 'single') {
                $defaultOpt = $var->options->firstWhere('is_default', true) ?? ($var->is_required ? $var->options->first() : null);
                $this->selectedVariationOptions[$var->id] = $defaultOpt ? $defaultOpt->id : null;
            } else {
                $defaultOpts = $var->options->where('is_default', true)->pluck('id')->toArray();
                $this->selectedVariationOptions[$var->id] = $defaultOpts;
            }
        }

        $this->dispatch('open-modal', id: 'variation-modal');
    }

    public function selectVariationOption(int $variationId, int $optionId, string $type)
    {
        if ($type === 'single') {
            $this->selectedVariationOptions[$variationId] = $optionId;
        } else {
            $current = $this->selectedVariationOptions[$variationId] ?? [];
            if (in_array($optionId, $current)) {
                $current = array_values(array_diff($current, [$optionId]));
            } else {
                $current[] = $optionId;
            }
            $this->selectedVariationOptions[$variationId] = $current;
        }
    }

    public function incrementVariationQty()
    {
        $this->variationQty++;
    }

    public function decrementVariationQty()
    {
        if ($this->variationQty > 1) {
            $this->variationQty--;
        }
    }

    public function getVariationTotalPriceProperty(): float
    {
        if (! $this->selectedProductForVariation) {
            return 0;
        }

        $basePrice = (float) $this->selectedProductForVariation->selling_price;
        $extraPrice = 0;

        foreach ($this->selectedProductForVariation->variations as $var) {
            if ($var->type === 'single') {
                $optId = $this->selectedVariationOptions[$var->id] ?? null;
                if ($optId) {
                    $opt = $var->options->firstWhere('id', $optId);
                    if ($opt) {
                        $extraPrice += (float) $opt->price;
                    }
                }
            } else {
                $optIds = $this->selectedVariationOptions[$var->id] ?? [];
                foreach ($optIds as $optId) {
                    $opt = $var->options->firstWhere('id', $optId);
                    if ($opt) {
                        $extraPrice += (float) $opt->price;
                    }
                }
            }
        }

        return ($basePrice + $extraPrice) * max(1, $this->variationQty);
    }

    public function getCanSubmitVariationsProperty(): bool
    {
        if (! $this->selectedProductForVariation) {
            return false;
        }

        foreach ($this->selectedProductForVariation->variations as $var) {
            if ($var->is_required) {
                if ($var->type === 'single') {
                    if (empty($this->selectedVariationOptions[$var->id])) {
                        return false;
                    }
                } else {
                    $selected = $this->selectedVariationOptions[$var->id] ?? [];
                    if (empty($selected) || count($selected) < max(1, $var->min ?? 1)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function confirmAddVariationCart()
    {
        if (! $this->canSubmitVariations || ! $this->selectedProductForVariation) {
            return;
        }

        $selectedVariationsPayload = [];
        foreach ($this->selectedProductForVariation->variations as $var) {
            if ($var->type === 'single') {
                $selectedOptId = $this->selectedVariationOptions[$var->id] ?? null;
                if ($selectedOptId) {
                    $opt = $var->options->firstWhere('id', $selectedOptId);
                    if ($opt) {
                        $selectedVariationsPayload[] = [
                            'variation_id' => $var->id,
                            'variation_name' => $var->name,
                            'option_id' => $opt->id,
                            'option_name' => $opt->name,
                            'price' => (float) $opt->price,
                        ];
                    }
                }
            } else {
                $selectedOptIds = $this->selectedVariationOptions[$var->id] ?? [];
                foreach ($selectedOptIds as $selectedOptId) {
                    $opt = $var->options->firstWhere('id', $selectedOptId);
                    if ($opt) {
                        $selectedVariationsPayload[] = [
                            'variation_id' => $var->id,
                            'variation_name' => $var->name,
                            'option_id' => $opt->id,
                            'option_name' => $opt->name,
                            'price' => (float) $opt->price,
                        ];
                    }
                }
            }
        }

        $this->addCartWithVariations($this->selectedProductForVariation, $this->variationQty, $selectedVariationsPayload);

        $this->dispatch('close-modal', id: 'variation-modal');
        $this->selectedProductForVariation = null;
        $this->selectedVariationOptions = [];
        $this->variationQty = 1;
    }

    public function addCartWithVariations(Product $product, int $qty, array $selectedVariations = [])
    {
        $auth = pos_user_id();

        if (! $this->validateStock($product, $qty)) {
            return;
        }

        $extraPrice = collect($selectedVariations)->sum('price');
        $unitPrice = $product->selling_price + $extraPrice;

        $existingCartItem = CartItem::whereProductId($product->getKey())
            ->cashier()
            ->get()
            ->first(function (CartItem $item) use ($selectedVariations) {
                return $this->areVariationsEqual($item->selected_variations, $selectedVariations);
            });

        if ($existingCartItem) {
            $newQty = $existingCartItem->qty + $qty;
            if (! $this->validateStock($product, $newQty)) {
                return;
            }
            $existingCartItem->qty = $newQty;
            $existingCartItem->price = $unitPrice * $newQty;
            $existingCartItem->save();
        } else {
            CartItem::create([
                'user_id' => $auth,
                'product_id' => $product->getKey(),
                'qty' => $qty,
                'price' => $unitPrice * $qty,
                'selected_variations' => !empty($selectedVariations) ? $selectedVariations : null,
            ]);
        }

        $this->mount();
    }

    private function areVariationsEqual($varA, $varB): bool
    {
        $normalize = function ($vars) {
            if (empty($vars)) {
                return [];
            }
            $items = is_string($vars) ? json_decode($vars, true) : (array) $vars;
            $options = array_map(function ($item) {
                return ($item['variation_id'] ?? 0) . ':' . ($item['option_id'] ?? 0);
            }, $items);
            sort($options);
            return $options;
        };

        return $normalize($varA) === $normalize($varB);
    }

    public function increaseCart($cartItem)
    {
        $item = $cartItem instanceof CartItem ? $cartItem : CartItem::find($cartItem);
        if (! $item) {
            return;
        }

        $product = $item->product;
        $qty = $item->qty + 1;
        $otherQty = CartItem::whereProductId($product->getKey())
            ->where('id', '!=', $item->id)
            ->cashier()
            ->sum('qty');

        if (! $this->validateStock($product, $otherQty + $qty)) {
            return;
        }

        $unitPrice = $item->price / $item->qty;
        $item->qty = $qty;
        $item->price = $unitPrice * $qty;
        $item->save();

        $this->mount();
    }

    public function toggleCartItemVariationOption(int $cartItemId, int $variationId, int $optionId, string $type)
    {
        $cartItem = CartItem::with('product.variations.options')->find($cartItemId);
        if (! $cartItem || ! $cartItem->product) {
            return;
        }

        $variation = $cartItem->product->variations->firstWhere('id', $variationId);
        $option = $variation?->options->firstWhere('id', $optionId);

        if (! $variation || ! $option) {
            return;
        }

        $current = $cartItem->selected_variations ?? [];

        if ($type === 'single') {
            $alreadySelected = collect($current)->first(function ($v) use ($variationId, $optionId) {
                return ($v['variation_id'] == $variationId) && ($v['option_id'] == $optionId);
            });

            $current = array_values(array_filter($current, function ($v) use ($variationId) {
                return $v['variation_id'] != $variationId;
            }));

            if (! $alreadySelected || $variation->is_required) {
                $current[] = [
                    'variation_id' => $variation->id,
                    'variation_name' => $variation->name,
                    'option_id' => $option->id,
                    'option_name' => $option->name,
                    'price' => (float) $option->price,
                ];
            }
        } else {
            $exists = false;
            $newCurrent = [];
            foreach ($current as $v) {
                if ($v['variation_id'] == $variationId && $v['option_id'] == $optionId) {
                    $exists = true;
                } else {
                    $newCurrent[] = $v;
                }
            }

            if (! $exists) {
                $newCurrent[] = [
                    'variation_id' => $variation->id,
                    'variation_name' => $variation->name,
                    'option_id' => $option->id,
                    'option_name' => $option->name,
                    'price' => (float) $option->price,
                ];
            }
            $current = $newCurrent;
        }

        $extraPrice = collect($current)->sum('price');
        $unitPrice = (float) $cartItem->product->selling_price + $extraPrice;

        $cartItem->price = $unitPrice * $cartItem->qty;
        $cartItem->selected_variations = !empty($current) ? $current : null;
        $cartItem->save();

        $this->expandedVariationCartItemId = $cartItemId;
        $this->mount();
    }

    public function clearCartItemVariation(int $cartItemId, int $variationId)
    {
        $cartItem = CartItem::with('product.variations.options')->find($cartItemId);
        if (! $cartItem || ! $cartItem->product) {
            return;
        }

        $current = array_values(array_filter($cartItem->selected_variations ?? [], function ($v) use ($variationId) {
            return $v['variation_id'] != $variationId;
        }));

        $extraPrice = collect($current)->sum('price');
        $unitPrice = (float) $cartItem->product->selling_price + $extraPrice;

        $cartItem->price = $unitPrice * $cartItem->qty;
        $cartItem->selected_variations = !empty($current) ? $current : null;
        $cartItem->save();

        $this->expandedVariationCartItemId = $cartItemId;
        $this->mount();
    }

    public function cartItemHasMissingRequired(CartItem $item): bool
    {
        return $item->hasMissingRequiredVariations();
    }

    public function hasMissingRequiredVariations(): bool
    {
        if (! isset($this->cartItems) || ! $this->cartItems) {
            return false;
        }

        foreach ($this->cartItems as $item) {
            if ($item->hasMissingRequiredVariations()) {
                return true;
            }
        }

        return false;
    }

    public function getHasMissingRequiredVariationsProperty(): bool
    {
        return $this->hasMissingRequiredVariations();
    }

    public function notifyMissingVariations()
    {
        Notification::make()
            ->title(__('Variasi Wajib Belum Dipilih'))
            ->body(__('Harap lengkapi semua variasi bertanda * Wajib pada item pesanan terlebih dahulu sebelum melanjutkan pembayaran.'))
            ->warning()
            ->send();
    }

    public function addCart(Product $product, ?array $data = null)
    {
        $auth = pos_user_id();
        $product->loadMissing('variations.options');

        if ($product->variations->isNotEmpty()) {
            $qty = $data['amount'] ?? 1;
            $existingQty = CartItem::whereProductId($product->getKey())
                ->cashier()
                ->sum('qty');

            if (! $this->validateStock($product, $existingQty + $qty)) {
                return;
            }

            $defaultVariations = [];
            foreach ($product->variations as $var) {
                if ($var->type === 'single') {
                    $defaultOpt = $var->options->firstWhere('is_default', true);
                    if ($defaultOpt) {
                        $defaultVariations[] = [
                            'variation_id' => $var->id,
                            'variation_name' => $var->name,
                            'option_id' => $defaultOpt->id,
                            'option_name' => $defaultOpt->name,
                            'price' => (float) $defaultOpt->price,
                        ];
                    }
                } else {
                    foreach ($var->options->where('is_default', true) as $defaultOpt) {
                        $defaultVariations[] = [
                            'variation_id' => $var->id,
                            'variation_name' => $var->name,
                            'option_id' => $defaultOpt->id,
                            'option_name' => $defaultOpt->name,
                            'price' => (float) $defaultOpt->price,
                        ];
                    }
                }
            }

            $extraPrice = collect($defaultVariations)->sum('price');
            $unitPrice = (float) $product->selling_price + $extraPrice;

            $newCartItem = CartItem::create([
                'product_id' => $product->getKey(),
                'user_id' => $auth,
                'qty' => $qty,
                'price' => $unitPrice * $qty,
                'selected_variations' => !empty($defaultVariations) ? $defaultVariations : null,
            ]);
            $this->expandedVariationCartItemId = $newCartItem->id;
            $this->mount();
            return;
        }

        if (! $data) {
            $qty = (
                CartItem::whereProductId($product->getKey())
                    ->whereNull('selected_variations')
                    ->cashier()
                    ->first()
                ->qty ?? 0
            ) + 1;
        } else {
            if (!$data['amount']) {
                $this->deleteCart(CartItem::whereProductId($product->getKey())->first());
                $this->mount();
                return;
            }
            $qty = $data['amount'];
        }
        if (! $this->validateStock($product, $qty)) {
            return;
        }
        CartItem::query()
            ->updateOrCreate(
                [
                    'product_id' => $product->getKey(),
                    'user_id' => $auth,
                    'selected_variations' => null,
                ],
                [
                    'qty' => $qty,
                    'price' => $product->selling_price * $qty,
                    'user_id' => $auth,
                    'product_id' => $product->getKey(),
                ]
            );
        $this->mount();
    }

    public function reduceCart($target)
    {
        $cartItem = $target instanceof CartItem ? $target : CartItem::find($target);

        if (! $cartItem && is_numeric($target)) {
            $cartItem = CartItem::whereProductId($target)->cashier()->first();
        }

        if (! $cartItem) {
            return;
        }

        $qty = $cartItem->qty - 1;
        if ($qty <= 0) {
            $cartItem->delete();

            $this->mount();

            return;
        }
        $unitPrice = $cartItem->price / $cartItem->qty;
        $cartItem->qty = $qty;
        $cartItem->price = $unitPrice * $qty;
        $cartItem->save();
        $this->mount();
    }

    public function updateCartItemQty($target, $amount)
    {
        $cartItem = $target instanceof CartItem ? $target : CartItem::find($target);

        if (! $cartItem && is_numeric($target)) {
            $cartItem = CartItem::whereProductId($target)->cashier()->first();
        }

        if (! $cartItem) {
            return;
        }

        $qty = (float) $amount;
        if ($qty <= 0) {
            $cartItem->delete();
            $this->mount();
            return;
        }

        $otherQty = CartItem::whereProductId($cartItem->product_id)
            ->where('id', '!=', $cartItem->id)
            ->cashier()
            ->sum('qty');

        if (! $this->validateStock($cartItem->product, $otherQty + $qty)) {
            return;
        }

        $unitPrice = $cartItem->price / $cartItem->qty;
        $cartItem->qty = $qty;
        $cartItem->price = $unitPrice * $qty;
        $cartItem->save();

        $this->mount();
    }

    public function deleteCart(CartItem $cartItem)
    {
        $cartItem->delete();
        $this->mount();
    }

    public function updateCartItemNote(int $cartItemId, ?string $note): void
    {
        $cartItem = CartItem::find($cartItemId);
        if (! $cartItem) {
            return;
        }

        $cartItem->note = filled($note) ? trim($note) : null;
        $cartItem->save();
        $this->mount();
    }

    public function addDiscountPricePerItem(CartItem $cartItem, $value)
    {
        if (! ($value && $value > 0) && $value > $cartItem->product->selling_price) {
            return;
        }
        $cartItem->discount_price = (float) $value;

        $cartItem->save();
        $this->mount();
    }

    public function updateCart(CartItem $cartItem, $value)
    {
        if ((int) $value == 0) {
            $cartItem->delete();
            $this->mount();

            return;
        }
        $value = $value != '' ? $value : 0;
        if ($cartItem->product->qty <= $value) {
            Notification::make()
                ->title(__('Stock is out'))
                ->danger()
                ->send();
            $this->mount();

            return;
        }
        $price = $cartItem->product->selling_price * ((int) $value);
        $cartItem->fill([
            'qty' => $value,
            'price' => $price,
        ]);
        $cartItem->save();
        $this->mount();
    }

    public function clearCart()
    {
        CartItem::query()
            ->cashier()
            ->delete();

        $this->activeDraftId = null;
        $this->draftName = '';
        $this->orderType = null;
        $this->cartDetail = [];

        Notification::make()
            ->title(__('Cart has been cleared'))
            ->success()
            ->send();
        $this->mount();
    }

    public function setOrderType(string $type): void
    {
        if (! in_array($type, ['dine_in', 'take_away', 'ojek_online'], true)) {
            return;
        }

        $this->orderType = $type;
        $this->cartDetail['order_type'] = $type;
    }

    public function hasMissingOrderType(): bool
    {
        return empty($this->orderType);
    }

    public function notifyMissingOrderType(): void
    {
        Notification::make()
            ->title(__('Kategori Pesanan Wajib Dipilih'))
            ->body(__('Silakan pilih kategori pesanan (Dine In, Take Away, atau Ojek Online) terlebih dahulu.'))
            ->warning()
            ->send();
    }

    public function openProceedPaymentModal(): void
    {
        if (! isset($this->cartItems) || $this->cartItems->isEmpty()) {
            Notification::make()
                ->title(__('Keranjang Masih Kosong'))
                ->body(__('Silakan pilih item pesanan terlebih dahulu.'))
                ->warning()
                ->send();
            return;
        }

        if ($this->hasMissingRequiredVariations()) {
            $this->notifyMissingVariations();
            return;
        }

        if ($this->hasMissingOrderType()) {
            $this->notifyMissingOrderType();
            return;
        }

        $this->dispatch('open-modal', id: 'proceed-the-payment');
    }

    public function openSaveDraftModal(): void
    {
        if (! isset($this->cartItems) || $this->cartItems->isEmpty()) {
            Notification::make()
                ->title(__('Keranjang Masih Kosong'))
                ->body(__('Silakan pilih item pesanan terlebih dahulu sebelum menyimpan draft.'))
                ->warning()
                ->send();
            return;
        }

        if ($this->hasMissingRequiredVariations()) {
            $this->notifyMissingVariations();
            return;
        }

        if ($this->hasMissingOrderType()) {
            $this->notifyMissingOrderType();
            return;
        }

        if (blank($this->draftName)) {
            $orderLabel = match ($this->orderType) {
                'dine_in' => 'Dine In',
                'take_away' => 'Take Away',
                'ojek_online' => 'Ojek Online',
                default => 'Pesanan',
            };
            $tableId = $this->cartDetail['table_id'] ?? null;
            $table = $tableId ? Table::find($tableId) : null;
            $tablePart = $table ? (' - Meja ' . $table->number) : '';
            $this->draftName = $orderLabel . $tablePart . ' #' . (CartDraft::count() + 1);
        }

        $this->dispatch('open-modal', id: 'save-draft-modal');
    }

    public function quickSaveDraft(): void
    {
        if (! isset($this->cartItems) || $this->cartItems->isEmpty()) {
            Notification::make()
                ->title(__('Keranjang Masih Kosong'))
                ->body(__('Silakan pilih item pesanan terlebih dahulu sebelum menyimpan draft.'))
                ->warning()
                ->send();
            return;
        }

        if ($this->hasMissingRequiredVariations()) {
            $this->notifyMissingVariations();
            return;
        }

        if ($this->hasMissingOrderType()) {
            $this->notifyMissingOrderType();
            return;
        }

        if (blank($this->draftName)) {
            $orderLabel = match ($this->orderType) {
                'dine_in' => 'Dine In',
                'take_away' => 'Take Away',
                'ojek_online' => 'Ojek Online',
                default => 'Pesanan',
            };
            $tableId = $this->cartDetail['table_id'] ?? null;
            $table = $tableId ? Table::find($tableId) : null;
            $tablePart = $table ? (' - Meja ' . $table->number) : '';
            $this->draftName = $orderLabel . $tablePart . ' #' . (CartDraft::count() + 1);
        }

        $this->saveCartAsDraft();
    }

    public function saveCartAsDraft(): void
    {
        if (! isset($this->cartItems) || $this->cartItems->isEmpty()) {
            return;
        }

        if ($this->hasMissingRequiredVariations()) {
            $this->notifyMissingVariations();
            return;
        }

        if ($this->hasMissingOrderType()) {
            $this->notifyMissingOrderType();
            return;
        }

        if (blank($this->draftName)) {
            $orderLabel = match ($this->orderType) {
                'dine_in' => 'Dine In',
                'take_away' => 'Take Away',
                'ojek_online' => 'Ojek Online',
                default => 'Pesanan',
            };
            $tableId = $this->cartDetail['table_id'] ?? null;
            $table = $tableId ? Table::find($tableId) : null;
            $tablePart = $table ? (' - Meja ' . $table->number) : '';
            $this->draftName = $orderLabel . $tablePart . ' #' . (CartDraft::count() + 1);
        }

        $existingDraft = $this->activeDraftId ? CartDraft::find($this->activeDraftId) : null;
        $existingItems = $existingDraft ? ($existingDraft->items ?? []) : [];

        $buildSignature = function ($item): string {
            $productId = is_array($item) ? ($item['product_id'] ?? null) : $item->product_id;
            $variations = is_array($item) ? ($item['selected_variations'] ?? []) : ($item->selected_variations ?? []);
            $note = is_array($item) ? ($item['note'] ?? '') : ($item->note ?? '');
            $priceUnitId = is_array($item) ? ($item['price_unit_id'] ?? '') : ($item->price_unit_id ?? '');

            if (is_array($variations)) {
                usort($variations, fn ($a, $b) => ($a['option_id'] ?? 0) <=> ($b['option_id'] ?? 0));
            }

            return $productId . '|' . $priceUnitId . '|' . md5(json_encode($variations)) . '|' . trim((string) $note);
        };

        $existingPrintedQtyMap = [];
        foreach ($existingItems as $exItem) {
            $sig = $buildSignature($exItem);
            $qty = floatval($exItem['printed_qty'] ?? $exItem['qty'] ?? 0);
            $existingPrintedQtyMap[$sig] = ($existingPrintedQtyMap[$sig] ?? 0) + $qty;
        }

        $itemsPayload = [];
        $itemsToPrint = [];

        $productIds = $this->cartItems->pluck('product_id')->unique()->toArray();
        $products = Product::with('category')->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($this->cartItems as $cartItem) {
            $sig = $buildSignature($cartItem);
            $alreadyPrinted = $existingPrintedQtyMap[$sig] ?? 0;
            $currentQty = floatval($cartItem->qty);
            $unprintedQty = max(0, $currentQty - $alreadyPrinted);

            $itemsPayload[] = [
                'product_id' => $cartItem->product_id,
                'qty' => $cartItem->qty,
                'price' => $cartItem->price,
                'discount_price' => $cartItem->discount_price,
                'price_unit_id' => $cartItem->price_unit_id,
                'selected_variations' => $cartItem->selected_variations,
                'note' => $cartItem->note,
                'printed_qty' => $currentQty,
            ];

            if ($unprintedQty > 0) {
                $product = $products->get($cartItem->product_id);
                $itemsToPrint[] = [
                    'product_id' => $cartItem->product_id,
                    'product' => [
                        'id' => $product?->id,
                        'name' => $product?->name ?? 'Item',
                        'category_id' => $product?->category_id,
                        'category_name' => $product?->category?->name ?? null,
                    ],
                    'qty' => $unprintedQty,
                    'total_qty' => $currentQty,
                    'is_additional' => ($alreadyPrinted > 0 || ! empty($existingDraft)),
                    'price' => $cartItem->price,
                    'selected_variations' => $cartItem->selected_variations,
                    'note' => $cartItem->note,
                ];
            }
        }

        $draftData = [
            'user_id' => pos_user_id(),
            'name' => filled($this->draftName) ? trim($this->draftName) : ('Draft #' . (CartDraft::count() + 1)),
            'order_type' => $this->orderType,
            'member_id' => $this->cartDetail['member_id'] ?? null,
            'table_id' => $this->cartDetail['table_id'] ?? null,
            'voucher' => $this->cartDetail['voucher'] ?? null,
            'discount_price' => floatval(str_replace(',', '', (string) ($this->cartDetail['discount_price'] ?? 0))),
            'note' => $this->cartDetail['note'] ?? null,
            'total_price' => $this->total_price,
            'items' => $itemsPayload,
            'is_kitchen_printed' => ! empty($itemsToPrint) ? true : (bool) ($existingDraft?->is_kitchen_printed ?? false),
            'kitchen_printed_at' => ! empty($itemsToPrint) ? now() : $existingDraft?->kitchen_printed_at,
        ];

        if ($existingDraft) {
            $existingDraft->update($draftData);
            $savedDraft = $existingDraft->fresh(['table', 'member', 'user']);
        } else {
            $savedDraft = CartDraft::create($draftData)->load(['table', 'member', 'user']);
        }

        $draftPayload = [
            'id' => $savedDraft->id,
            'name' => $savedDraft->name,
            'order_type' => $savedDraft->order_type,
            'order_type_label' => $savedDraft->order_type_label,
            'table' => $savedDraft->table ? ['number' => $savedDraft->table->number] : null,
            'user' => ['name' => Filament::auth()->user()?->name ?? 'Kasir'],
            'created_at' => now()->format('d/m/Y H:i'),
            'items_to_print' => $itemsToPrint,
            'has_additions' => collect($itemsToPrint)->contains('is_additional', true),
            'is_reprint' => false,
        ];

        CartItem::query()->cashier()->delete();

        $this->activeDraftId = null;
        $this->draftName = '';
        $this->orderType = null;
        $this->cartDetail = [];

        $this->dispatch('close-modal', id: 'save-draft-modal');

        Notification::make()
            ->title(__('Draft Pesanan Disimpan'))
            ->body(__('Pesanan berhasil disimpan ke daftar draft sementara.'))
            ->success()
            ->send();

        $this->mount();

        if (! empty($itemsToPrint)) {
            $this->dispatch('draft-saved', draft: $draftPayload);
            try {
                \App\Services\PrintBridgeService::queueDraftPrints($savedDraft, $itemsToPrint);
            } catch (\Throwable $e) {
                // Ignore background bridge queue error if not configured
            }
        }
    }

    public function printDraft(int $draftId, bool $forceAll = true): void
    {
        $draft = CartDraft::with(['table', 'user', 'member'])->find($draftId);
        if (! $draft) {
            return;
        }

        $items = $draft->items ?? [];
        if (empty($items)) {
            return;
        }

        $productIds = collect($items)->pluck('product_id')->unique()->toArray();
        $products = Product::with('category')->whereIn('id', $productIds)->get()->keyBy('id');

        $itemsToPrint = [];
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $itemsToPrint[] = [
                'product_id' => $item['product_id'],
                'product' => [
                    'id' => $product?->id,
                    'name' => $product?->name ?? 'Item',
                    'category_id' => $product?->category_id,
                    'category_name' => $product?->category?->name ?? null,
                ],
                'qty' => $item['qty'] ?? 1,
                'total_qty' => $item['qty'] ?? 1,
                'is_additional' => false,
                'price' => $item['price'] ?? 0,
                'selected_variations' => $item['selected_variations'] ?? [],
                'note' => $item['note'] ?? null,
            ];
        }

        $draftPayload = [
            'id' => $draft->id,
            'name' => $draft->name,
            'order_type' => $draft->order_type,
            'order_type_label' => $draft->order_type_label,
            'table' => $draft->table ? ['number' => $draft->table->number] : null,
            'user' => ['name' => $draft->user?->name ?? Filament::auth()->user()?->name ?? 'Kasir'],
            'created_at' => $draft->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
            'items_to_print' => $itemsToPrint,
            'has_additions' => false,
            'is_reprint' => true,
        ];

        $this->dispatch('draft-saved', draft: $draftPayload);

        try {
            \App\Services\PrintBridgeService::queueDraftPrints($draft, $itemsToPrint);
        } catch (\Throwable $e) {
            // Ignore background bridge queue error if not configured
        }

        $draft->update([
            'is_kitchen_printed' => true,
            'kitchen_printed_at' => $draft->kitchen_printed_at ?? now(),
        ]);

        if (method_exists($this, 'refreshPosDrafts')) {
            $this->refreshPosDrafts();
        }

        Notification::make()
            ->title(__('Tiket Draft Dikirim ke Printer'))
            ->body(__('Tiket draft ":name" berhasil dikirim ke printer station terkait.', ['name' => $draft->name]))
            ->success()
            ->send();
    }

    public function saveFrontendDraft(array $payload, bool $clearCartAfter = true): array
    {
        $items = $payload['items'] ?? [];
        if (empty($items)) {
            Notification::make()
                ->title(__('Keranjang Masih Kosong'))
                ->body(__('Silakan pilih item pesanan terlebih dahulu sebelum menyimpan draft.'))
                ->warning()
                ->send();

            return ['ok' => false];
        }

        $orderType = $payload['order_type'] ?? null;
        if (empty($orderType) || ! in_array($orderType, ['dine_in', 'take_away', 'ojek_online'], true)) {
            $this->notifyMissingOrderType();

            return ['ok' => false];
        }

        $productIds = collect($items)->pluck('product_id')->unique()->filter()->all();
        $products = Product::with(['category', 'variations'])->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get($item['product_id'] ?? null);
            if (! $product) {
                continue;
            }
            $selected = collect($item['selected_variations'] ?? []);
            foreach ($product->variations as $var) {
                if ($var->is_required) {
                    $count = $selected->where('variation_id', $var->id)->count();
                    if ($count < max(1, $var->min ?? 1)) {
                        $this->notifyMissingVariations();

                        return ['ok' => false];
                    }
                }
            }
        }

        $draftName = trim((string) ($payload['draft_name'] ?? ''));
        $tableId = ! empty($payload['table_id']) ? (int) $payload['table_id'] : null;

        if (blank($draftName)) {
            $orderLabel = match ($orderType) {
                'dine_in' => 'Dine In',
                'take_away' => 'Take Away',
                'ojek_online' => 'Ojek Online',
                default => 'Pesanan',
            };
            $table = $tableId ? Table::find($tableId) : null;
            $tablePart = $table ? (' - Meja ' . $table->number) : '';
            $draftName = $orderLabel . $tablePart . ' #' . (CartDraft::count() + 1);
        }

        $activeDraftId = ! empty($payload['active_draft_id']) ? (int) $payload['active_draft_id'] : null;
        $existingDraft = $activeDraftId ? CartDraft::find($activeDraftId) : null;
        $existingItems = $existingDraft ? ($existingDraft->items ?? []) : [];
        $alreadyKitchenPrinted = (bool) ($existingDraft?->is_kitchen_printed ?? false);

        $buildSignature = function ($item): string {
            $productId = is_array($item) ? ($item['product_id'] ?? null) : $item->product_id;
            $variations = is_array($item) ? ($item['selected_variations'] ?? []) : ($item->selected_variations ?? []);
            $note = is_array($item) ? ($item['note'] ?? '') : ($item->note ?? '');
            $priceUnitId = is_array($item) ? ($item['price_unit_id'] ?? '') : ($item->price_unit_id ?? '');

            if (is_array($variations)) {
                usort($variations, fn ($a, $b) => ($a['option_id'] ?? 0) <=> ($b['option_id'] ?? 0));
            }

            return $productId . '|' . $priceUnitId . '|' . md5(json_encode($variations)) . '|' . trim((string) $note);
        };

        $existingPrintedQtyMap = [];
        $existingPrintedMetaMap = [];
        foreach ($existingItems as $exItem) {
            $sig = $buildSignature($exItem);
            $exPrintedQty = floatval($exItem['printed_qty'] ?? ($alreadyKitchenPrinted ? ($exItem['qty'] ?? 0) : 0));
            $existingPrintedQtyMap[$sig] = ($existingPrintedQtyMap[$sig] ?? 0) + $exPrintedQty;
            if (! empty($exItem['is_kitchen_printed'])) {
                $existingPrintedMetaMap[$sig] = $exItem['kitchen_printed_at'] ?? null;
            }
        }

        $hasAnyConfiguredPrinter = \App\Models\Tenants\Printer::active()->exists();
        $draftPrinters = \App\Models\Tenants\Printer::active()
            ->get()
            ->filter(fn ($p) => (bool) ($p->print_on_draft ?? ! $p->show_price));

        $itemsPayload = [];
        $itemsToPrint = [];

        foreach ($items as $item) {
            $sig = $buildSignature($item);
            $product = $products->get($item['product_id']);
            $categoryId = $product?->category_id ? (int) $product->category_id : null;
            $matchesKitchenPrinter = ! $hasAnyConfiguredPrinter
                ? true
                : $draftPrinters->contains(fn ($p) => $p->appliesToCategory($categoryId));

            $alreadyPrinted = $existingPrintedQtyMap[$sig] ?? 0;
            $currentQty = floatval($item['qty'] ?? 1);
            $unprintedQty = $matchesKitchenPrinter ? max(0, $currentQty - $alreadyPrinted) : 0;
            $itemIsKitchenPrinted = $matchesKitchenPrinter && ($currentQty > 0);
            $itemKitchenPrintedAt = $unprintedQty > 0
                ? now()->toIso8601String()
                : ($existingPrintedMetaMap[$sig] ?? ($itemIsKitchenPrinted ? now()->toIso8601String() : null));

            $itemsPayload[] = [
                'product_id' => $item['product_id'],
                'qty' => $currentQty,
                'price' => floatval($item['price'] ?? 0),
                'discount_price' => floatval($item['discount_price'] ?? 0),
                'price_unit_id' => $item['price_unit_id'] ?? null,
                'selected_variations' => $item['selected_variations'] ?? [],
                'note' => $item['note'] ?? null,
                'printed_qty' => $matchesKitchenPrinter ? $currentQty : 0,
                'is_kitchen_printed' => $itemIsKitchenPrinted,
                'kitchen_printed_at' => $itemKitchenPrintedAt,
            ];

            if ($unprintedQty > 0) {
                $itemsToPrint[] = [
                    'product_id' => $item['product_id'],
                    'product' => [
                        'id' => $product?->id,
                        'name' => $product?->name ?? 'Item',
                        'category_id' => $product?->category_id,
                        'category_name' => $product?->category?->name ?? null,
                    ],
                    'qty' => $unprintedQty,
                    'total_qty' => $currentQty,
                    'is_additional' => ($alreadyPrinted > 0 || ($alreadyKitchenPrinted && ! empty($existingDraft))),
                    'price' => floatval($item['price'] ?? 0),
                    'selected_variations' => $item['selected_variations'] ?? [],
                    'note' => $item['note'] ?? null,
                ];
            }
        }

        $didPrint = ! empty($itemsToPrint);

        $draftData = [
            'user_id' => pos_user_id(),
            'name' => $draftName,
            'order_type' => $orderType,
            'member_id' => ! empty($payload['member_id']) ? (int) $payload['member_id'] : null,
            'table_id' => $tableId,
            'voucher' => $payload['voucher'] ?? null,
            'discount_price' => floatval($payload['discount_price'] ?? 0),
            'note' => $payload['note'] ?? null,
            'total_price' => floatval($payload['total_price'] ?? 0),
            'items' => $itemsPayload,
            'is_kitchen_printed' => $didPrint ? true : $alreadyKitchenPrinted,
            'kitchen_printed_at' => $didPrint ? now() : $existingDraft?->kitchen_printed_at,
        ];

        if ($existingDraft) {
            $existingDraft->update($draftData);
            $savedDraft = $existingDraft->fresh(['table', 'member', 'user']);
        } else {
            $savedDraft = CartDraft::create($draftData)->load(['table', 'member', 'user']);
        }

        if ($didPrint) {
            $draftPayload = [
                'id' => $savedDraft->id,
                'name' => $savedDraft->name,
                'order_type' => $savedDraft->order_type,
                'order_type_label' => $savedDraft->order_type_label,
                'table' => $savedDraft->table ? ['number' => $savedDraft->table->number] : null,
                'user' => ['name' => Filament::auth()->user()?->name ?? 'Kasir'],
                'created_at' => now()->format('d/m/Y H:i'),
                'items_to_print' => $itemsToPrint,
                'has_additions' => collect($itemsToPrint)->contains('is_additional', true),
                'is_reprint' => false,
            ];

            $this->dispatch('draft-saved', draft: $draftPayload);
            try {
                \App\Services\PrintBridgeService::queueDraftPrints($savedDraft, $itemsToPrint);
            } catch (\Throwable $e) {
                // Ignore background bridge queue error if not configured
            }
        }

        if ($clearCartAfter) {
            CartItem::query()->cashier()->delete();
            $this->activeDraftId = null;
            $this->draftName = '';
            $this->orderType = null;
            $this->cartDetail = [];

            Notification::make()
                ->title(__('Draft Pesanan Disimpan'))
                ->body(__('Pesanan berhasil disimpan ke daftar draft sementara.'))
                ->success()
                ->send();
        } else {
            $this->activeDraftId = $savedDraft->id;
            $this->draftName = $savedDraft->name ?? '';
        }

        if (method_exists($this, 'refreshPosDrafts')) {
            $this->refreshPosDrafts();
        }

        return [
            'ok' => true,
            'clear_cart' => $clearCartAfter,
            'active_draft_id' => $savedDraft->id,
            'draft_name' => $savedDraft->name,
            'is_kitchen_printed' => (bool) $savedDraft->is_kitchen_printed,
            'printed_to_kitchen' => $didPrint,
            'drafts' => $this->posDrafts ?? [],
        ];
    }

    public function deleteFrontendDraft(int $draftId): array
    {
        CartDraft::whereKey($draftId)->delete();

        if ($this->activeDraftId === $draftId) {
            $this->activeDraftId = null;
            $this->draftName = '';
        }

        if (method_exists($this, 'refreshPosDrafts')) {
            $this->refreshPosDrafts();
        }

        Notification::make()
            ->title(__('Draft Dihapus'))
            ->success()
            ->send();

        return [
            'ok' => true,
            'drafts' => $this->posDrafts ?? [],
        ];
    }

    public function printFrontendDraft(int $draftId): array
    {
        $this->printDraft($draftId, true);

        return [
            'ok' => true,
            'drafts' => $this->posDrafts ?? [],
        ];
    }

    public function loadDraft(int $draftId): void
    {
        $draft = CartDraft::find($draftId);
        if (! $draft) {
            return;
        }

        $auth = pos_user_id();

        CartItem::query()->cashier()->delete();

        foreach ($draft->items ?? [] as $itemData) {
            CartItem::create([
                'user_id' => $auth,
                'product_id' => $itemData['product_id'],
                'qty' => $itemData['qty'] ?? 1,
                'price' => $itemData['price'] ?? 0,
                'discount_price' => $itemData['discount_price'] ?? 0,
                'price_unit_id' => $itemData['price_unit_id'] ?? null,
                'selected_variations' => $itemData['selected_variations'] ?? null,
                'note' => $itemData['note'] ?? null,
            ]);
        }

        $this->activeDraftId = $draft->id;
        $this->draftName = $draft->name ?? '';
        $this->orderType = $draft->order_type;
        $this->cartDetail['member_id'] = $draft->member_id;
        $this->cartDetail['table_id'] = $draft->table_id;
        $this->cartDetail['voucher'] = $draft->voucher;
        $this->cartDetail['discount_price'] = $draft->discount_price > 0 ? (string) $draft->discount_price : null;
        $this->cartDetail['note'] = $draft->note;
        $this->cartDetail['order_type'] = $draft->order_type;

        $this->mount();
        $this->fillMember();

        $this->dispatch('close-modal', id: 'draft-list-modal');

        Notification::make()
            ->title(__('Draft Pesanan Dimuat'))
            ->body(__('Draft ":name" berhasil dimuat ke keranjang.', ['name' => $draft->name]))
            ->success()
            ->send();
    }

    public function deleteDraft(int $draftId): void
    {
        CartDraft::whereKey($draftId)->delete();

        if ($this->activeDraftId === $draftId) {
            $this->activeDraftId = null;
            $this->draftName = '';
        }

        Notification::make()
            ->title(__('Draft Dihapus'))
            ->success()
            ->send();

        $this->mount();
    }

    public function addCartUsingScanner(string $value)
    {
        $product = Product::findByBarcodeOrSku($value);

        if (! $product) {
            Notification::make()
                ->title(__('Product not found'))
                ->warning()
                ->send();

            return;
        }

        $product->loadMissing('variations.options');

        if ($product->variations->isNotEmpty()) {
            $this->openVariationModal($product);
            return;
        }

        $stock = 1;

        $cartItem = CartItem::whereProductId($product->getKey())
            ->cashier()
            ->first();
        if ($cartItem) {
            $stock = $cartItem->qty + 1;
        }

        $this->addCart($product, [
            'amount' => $stock,
        ]);
    }
}
