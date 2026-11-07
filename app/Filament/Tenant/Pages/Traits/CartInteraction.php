<?php

namespace App\Filament\Tenant\Pages\Traits;

use App\Models\Tenants\CartDraft;
use App\Models\Tenants\CartItem;
use App\Models\Tenants\Product;
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
        $auth = Filament::auth()->id();

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
        $auth = Filament::auth()->id();
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
            $this->draftName = $orderLabel . ' #' . (CartDraft::count() + 1);
        }

        $this->dispatch('open-modal', id: 'save-draft-modal');
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

        $itemsPayload = $this->cartItems->map(fn (CartItem $cartItem) => [
            'product_id' => $cartItem->product_id,
            'qty' => $cartItem->qty,
            'price' => $cartItem->price,
            'discount_price' => $cartItem->discount_price,
            'price_unit_id' => $cartItem->price_unit_id,
            'selected_variations' => $cartItem->selected_variations,
            'note' => $cartItem->note,
        ])->values()->toArray();

        $draftData = [
            'user_id' => Filament::auth()->id(),
            'name' => filled($this->draftName) ? trim($this->draftName) : ('Draft #' . (CartDraft::count() + 1)),
            'order_type' => $this->orderType,
            'member_id' => $this->cartDetail['member_id'] ?? null,
            'table_id' => $this->cartDetail['table_id'] ?? null,
            'voucher' => $this->cartDetail['voucher'] ?? null,
            'discount_price' => floatval(str_replace(',', '', (string) ($this->cartDetail['discount_price'] ?? 0))),
            'note' => $this->cartDetail['note'] ?? null,
            'total_price' => $this->total_price,
            'items' => $itemsPayload,
        ];

        if ($this->activeDraftId && ($existingDraft = CartDraft::find($this->activeDraftId))) {
            $existingDraft->update($draftData);
        } else {
            CartDraft::create($draftData);
        }

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
    }

    public function loadDraft(int $draftId): void
    {
        $draft = CartDraft::find($draftId);
        if (! $draft) {
            return;
        }

        $auth = Filament::auth()->id();

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
