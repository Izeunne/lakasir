<?php

namespace App\Filament\Tenant\Pages;

use App\Features\Member as FeaturesMember;
use App\Features\Voucher;
use App\Filament\Tenant\Pages\Traits\CartInteraction;
use App\Filament\Tenant\Pages\Traits\HasPosSecurity;
use App\Filament\Tenant\Pages\Traits\HasShiftManagement;
use App\Filament\Tenant\Pages\Traits\TableProduct;
use App\Filament\Tenant\Resources\Traits\RefreshThePage;
use App\Models\Tenants\About;
use App\Models\Tenants\CartDraft;
use App\Models\Tenants\CartItem;
use App\Models\Tenants\Category;
use App\Models\Tenants\Member;
use App\Models\Tenants\PaymentMethod;
use App\Models\Tenants\Product;
use App\Models\Tenants\Selling;
use App\Models\Tenants\Setting;
use App\Models\Tenants\Table;
use App\Models\Tenants\Voucher as TenantsVoucher;
use App\Rules\CheckProductStock;
use App\Rules\ShouldSameWithSellingDetail;
use App\Services\Tenants\SellingService;
use App\Services\VoucherService;
use App\Traits\HasTranslatableResource;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\RawJs;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as CollectionSupport;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\Tenants\Profile;

class Cashier extends Page implements HasForms, HasTable
{
    use CartInteraction, HasPosSecurity, HasShiftManagement, HasTranslatableResource, RefreshThePage, TableProduct;

    public static ?string $label = 'POS';

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static string $view = 'filament.tenant.pages.cashier';

    public static function canAccess(): bool
    {
        return can('create selling');
    }

    public Collection $cartItems;

    public Collection $cartDrafts;

    public Collection $availableVoucher;

    protected static string $layout = 'filament-panels::components.layout.base';

    public array $cartDetail = [];

    public ?string $orderType = null;

    public ?int $selectedCategoryId = null;

    public string $viewMode = 'grid';

    public function setCategory(?int $categoryId = null): void
    {
        $this->selectedCategoryId = $categoryId;
        $this->resetPage();
        $this->resetTable();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list', 'compact'], true) ? $mode : 'grid';
    }

    public ?int $activeDraftId = null;

    public string $draftName = '';

    public array $paymentMethods;

    public CollectionSupport $members;

    public float $tax;

    public string $currency;

    public string $locale;

    public float $sub_total = 0;

    public float $total_price = 0;

    public ?About $about;

    public ?Collection $tableOption;

    private float $discount_price = 0;

    public array $posProducts = [];

    public array $posCategories = [];

    public array $posVouchers = [];

    public array $posMembers = [];

    public array $posTables = [];

    public array $posDrafts = [];

    public array $posFeatures = [];

    public function mount()
    {
        $this->about = About::first() ?? null;

        $this->initializePosSecurity();
        $this->initializeShift();

        $this->tax = (float) Setting::get('default_tax', 0);

        $this->currency = Setting::get('currency', 'IDR');

        $this->locale = Profile::get()->locale ?? 'en';

        $this->cartItems = CartItem::query()
            ->select('*')
            ->with(['product.variations.options'])
            ->orderByDesc('created_at')
            ->cashier()
            ->get();

        $this->cartDrafts = CartDraft::query()
            ->with(['member', 'table', 'user'])
            ->latest()
            ->get();
        $vouchers = TenantsVoucher::query()
            ->where('minimal_buying', '<=', $this->cartItems->sum('price'))
            ->where('start_date', '<=', today()->format('Y-m-d'))
            ->where('expired', '>=', today()->format('Y-m-d'))
            ->get();

        $this->availableVoucher = $vouchers;

        $this->calculateTotalPrice();

        $this->paymentMethods = PaymentMethod::query()
            ->select('id', 'name', 'is_credit')
            ->get()
            ->toArray();

        $this->members = Member::query()
            ->select('id', 'name')
            ->get()
            ->pluck('name', 'id');

        $this->tableOption = Table::select('id', 'number')->get();

        $existingTableId = $this->cartDetail['table_id'] ?? null;
        $existingMemberId = $this->cartDetail['member_id'] ?? null;
        $existingMemberLabel = $this->cartDetail['member_label'] ?? null;
        $existingVoucher = $this->cartDetail['voucher'] ?? null;
        $existingDiscount = $this->cartDetail['discount_price'] ?? null;
        $existingNote = $this->cartDetail['note'] ?? null;

        $this->storeCartForm->fill([
            'payment_method_id' => $this->cartDetail['payment_method_id'] ?? 1,
            'total_price' => $this->total_price,
            'friend_price' => false,
            'member_id' => $existingMemberId,
            'voucher' => $existingVoucher,
            'discount_price' => $existingDiscount,
            'note' => $existingNote,
        ]);

        $this->cartDetail['table_id'] = $existingTableId;
        $this->cartDetail['member_label'] = $existingMemberLabel;
        $this->cartDetail['order_type'] = $this->orderType;

        $this->fillPayemntMethod();
        $this->loadPosBootstrapData();
    }

    public function loadPosBootstrapData(): void
    {
        $this->refreshPosProducts();

        $this->posCategories = Category::query()
            ->withCount(['products' => fn ($q) => $q->where('show', true)])
            ->having('products_count', '>', 0)
            ->get()
            ->map(fn (Category $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'products_count' => (int) $cat->products_count,
            ])
            ->values()
            ->all();

        $this->posVouchers = TenantsVoucher::query()
            ->where('start_date', '<=', today()->format('Y-m-d'))
            ->where('expired', '>=', today()->format('Y-m-d'))
            ->where('kuota', '>', 0)
            ->get()
            ->map(fn (TenantsVoucher $v) => [
                'id' => $v->id,
                'code' => $v->code,
                'type' => $v->type,
                'nominal' => (float) $v->nominal,
                'minimal_buying' => (float) $v->minimal_buying,
                'kuota' => (int) $v->kuota,
            ])
            ->values()
            ->all();

        $this->posMembers = Member::query()
            ->select('id', 'name', 'email', 'code')
            ->orderBy('name')
            ->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'code' => $m->code,
            ])
            ->values()
            ->all();

        $this->posTables = Table::query()
            ->select('id', 'number')
            ->orderBy('id')
            ->get()
            ->map(fn (Table $t) => [
                'id' => $t->id,
                'number' => $t->number,
            ])
            ->values()
            ->all();

        $this->refreshPosDrafts();

        $this->posFeatures = [
            'member' => (bool) hasFeatureAndPermission(FeaturesMember::class),
            'voucher' => (bool) hasFeatureAndPermission(Voucher::class),
            'discount' => (bool) feature(\App\Features\Discount::class),
            'selling_tax' => (bool) feature(\App\Features\SellingTax::class),
            'payment_shortcut' => (bool) feature(\App\Features\PaymentShortcutButton::class),
            'fnb' => ($this->about?->business_type === 'fnb'),
        ];
    }

    public function refreshPosProducts(): void
    {
        $products = Product::query()
            ->with(['variations.options', 'category', 'stocks', 'barcodes', 'priceUnits'])
            ->where(function ($query) {
                $query->where('type', 'product')
                    ->where(function ($query) {
                        $query->whereHas('stocks', function ($query) {
                            $query->where('is_ready', 1)
                                ->where('type', 'in')
                                ->where('stock', '>', 0);
                        })
                            ->orWhere('is_non_stock', true);
                    })
                    ->orWhere('type', 'service');
            })
            ->where('show', true)
            ->orderBy('name')
            ->get();

        $this->posProducts = $products->map(function (Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'selling_price' => (float) $product->selling_price,
                'hero_image' => $product->hero_image,
                'category_id' => $product->category_id,
                'category_name' => $product->category?->name,
                'is_non_stock' => (bool) $product->is_non_stock,
                'stock' => (float) $product->stock_calculate,
                'unit' => $product->unit,
                'barcodes' => $product->barcodes
                    ->where('is_active', true)
                    ->pluck('code')
                    ->values()
                    ->all(),
                'price_units' => $product->priceUnits
                    ->map(fn ($pu) => [
                        'id' => $pu->id,
                        'unit' => $pu->unit,
                        'selling_price' => (float) $pu->selling_price,
                        'stock' => (float) $pu->stock,
                    ])
                    ->values()
                    ->all(),
                'variations' => $product->variations
                    ->map(fn ($var) => [
                        'id' => $var->id,
                        'name' => $var->name,
                        'type' => $var->type,
                        'is_required' => (bool) $var->is_required,
                        'min' => (int) ($var->min ?? 0),
                        'max' => $var->max ? (int) $var->max : null,
                        'options' => $var->options
                            ->map(fn ($opt) => [
                                'id' => $opt->id,
                                'name' => $opt->name,
                                'price' => (float) $opt->price,
                                'is_default' => (bool) $opt->is_default,
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    public function refreshPosDrafts(): void
    {
        $drafts = CartDraft::query()
            ->with(['member', 'table', 'user'])
            ->latest()
            ->get();

        $this->cartDrafts = $drafts;

        $productIds = $drafts->flatMap(fn ($d) => collect($d->items ?? [])->pluck('product_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $productNames = Product::whereIn('id', $productIds)->pluck('name', 'id');

        $this->posDrafts = $drafts->map(function (CartDraft $draft) use ($productNames) {
            $items = collect($draft->items ?? [])->map(function ($item) use ($productNames) {
                $item['product_name'] = $productNames->get($item['product_id']) ?? 'Item';
                return $item;
            })->values()->all();

            return [
                'id' => $draft->id,
                'name' => $draft->name,
                'order_type' => $draft->order_type,
                'order_type_label' => $draft->order_type_label,
                'member_id' => $draft->member_id,
                'member_name' => $draft->member?->name,
                'table_id' => $draft->table_id,
                'table_number' => $draft->table?->number,
                'voucher' => $draft->voucher,
                'discount_price' => (float) $draft->discount_price,
                'note' => $draft->note,
                'total_price' => (float) $draft->total_price,
                'is_kitchen_printed' => (bool) $draft->is_kitchen_printed,
                'kitchen_printed_at' => $draft->kitchen_printed_at?->format('d M Y H:i'),
                'updated_at' => $draft->updated_at?->format('d M Y H:i'),
                'items' => $items,
            ];
        })->values()->all();
    }

    protected function getForms(): array
    {
        return [
            'storeCartForm',
        ];
    }

    public function storeCartForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('member_id')
                    ->visible(hasFeatureAndPermission(FeaturesMember::class))
                    ->label('Member')
                    ->getSearchResultsUsing(function (string $search): array {
                        return Member::query()
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->hiddenLabel()
                    ->extraAttributes([
                        'id' => 'memberSelect',
                        'class' => 'hidden',
                    ])
                    ->searchable(),
                RichEditor::make('note')
                    ->hiddenLabel()
                    ->extraAttributes([
                        'id' => 'noteInput',
                        'class' => 'hidden',
                    ]),
                TextInput::make('voucher')
                    ->hiddenLabel()
                    ->extraAttributes([
                        'id' => 'voucherInput',
                        'class' => 'hidden',
                    ])
                    ->visible(hasFeatureAndPermission(Voucher::class)),
                TextInput::make('discount_price')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(',')
                    ->numeric()
                    ->prefix(Setting::get('currency', 'IDR'))
                    ->extraAttributes([
                        'id' => 'discountInput',
                        'class' => 'hidden',
                    ])
                    ->hiddenLabel()
                    ->label(__('Manual Discount')),
            ])
            ->statePath('cartDetail')
            ->model(Selling::class);
    }

    public function storeCart(): void
    {
        if ($this->cartDetail['voucher']) {
            $this->validateVoucher($this->cartDetail['voucher']);
        }

        if ($discount_price = str_replace(',', '', $this->cartDetail['discount_price'])) {
            $this->cartItems->each(function (CartItem $item) {
                if ($item->discount_price && $item->discount_price > 0) {
                    $this->discount_price += $item->discount_price;
                }
            });
            $this->discount_price += floatval($discount_price);
            $this->total_price = $this->sub_total + ($this->sub_total * $this->tax / 100) - $this->discount_price;
        }
        $this->fillMember();
        $this->fillPayemntMethod();

        $this->dispatch('close-modal', id: 'edit-detail');
    }

    private function fillPayemntMethod()
    {
        $paymentMethod = collect($this->paymentMethods)->filter(function ($value, int $key) {
            return $value['id'] == $this->cartDetail['payment_method_id'];
        })->first();
        if (isset($paymentMethod['name'])) {
            $this->cartDetail['payment_method_label'] = $paymentMethod['name'];
        }
    }

    private function fillMember()
    {
        $member = $this->members->filter(function (string $value, int $key) {
            return $key == $this->cartDetail['member_id'];
        })->first();
        $this->cartDetail['member_label'] = $member;
    }

    public function proceedThePayment(SellingService $sellingService): void
    {
        if ($this->hasMissingRequiredVariations()) {
            Notification::make()
                ->title(__('Variasi Wajib Belum Dipilih'))
                ->body(__('Harap lengkapi semua variasi wajib pada item pesanan sebelum melanjutkan pembayaran.'))
                ->danger()
                ->send();
            return;
        }

        if ($this->hasMissingOrderType()) {
            $this->notifyMissingOrderType();
            return;
        }

        $this->cartDetail = array_merge($this->cartDetail, [
            'total_price' => $this->total_price,
            'order_type' => $this->orderType,
        ]);

        $request = array_merge($this->cartDetail, [
            'discount_price' => floatval(str_replace(',', '', (string) ($this->cartDetail['discount_price'] ?? 0))),
            'products' => $this->cartItems->map(function (CartItem $cartItem) {
                return [
                    'product_id' => $cartItem->product_id,
                    'qty' => $cartItem->qty,
                    'price' => $cartItem->price,
                    'discount_price' => $cartItem->discount_price,
                    'price_unit_id' => $cartItem->price_unit_id,
                    'selected_variations' => $cartItem->selected_variations,
                    'note' => $cartItem->note,
                ];
            })->toArray(),
        ]);

        $pMethod = PaymentMethod::find($request['payment_method_id']);
        if (! $pMethod) {
            $pMethod = PaymentMethod::create([
                'name' => 'Cash',
                'is_cash' => true,
                'is_debit' => false,
                'is_credit' => false,
                'is_wallet' => false,
                'icon' => 'assets/images/payment-methods/cash.png',
            ]);
        }
        $validator = Validator::make($request, [
            'fee' => ['numeric'],
            'payment_method_id' => ['required'],
            'member_id' => Rule::requiredIf(fn () => $pMethod->is_credit),
            'due_date' => Rule::requiredIf(fn () => $pMethod->is_credit),
            'payed_money' => [
                ! $pMethod->is_credit ? 'gte:total_price' : null,
                Rule::requiredIf(fn () => ! $pMethod->is_credit),
            ],
            'total_price' => ['required_if:friend_price,true', 'numeric'],
            'total_qty' => ['required_if:friend_price,true', 'numeric', new ShouldSameWithSellingDetail('qty', $request['products'])],
            'friend_price' => ['required', 'boolean'],
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.price' => ['required_if:friend_price,true', 'numeric'],
            'products.*.qty' => ['required', 'numeric', 'min:1', new CheckProductStock],
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->messages()->toArray());

            return;
        }
        $data = array_merge($request, $sellingService->mapProductRequest($request));
        $selling = $sellingService->create($data);
        CartItem::query()
            ->cashier()
            ->delete();

        if ($this->activeDraftId) {
            CartDraft::whereKey($this->activeDraftId)->delete();
        }

        $this->activeDraftId = null;
        $this->draftName = '';
        $this->orderType = null;
        $this->cartDetail = [];

        Notification::make()
            ->title(__('Transaction created'))
            ->success()
            ->send();

        $this->mount();

        $this->dispatch('selling-created', selling: $selling->load('sellingDetails.product.category', 'paymentMethod', 'member', 'table', 'user'));
    }

    public function proceedFrontendPayment(array $payload, SellingService $sellingService): array
    {
        $items = $payload['items'] ?? [];
        if (empty($items)) {
            Notification::make()
                ->title(__('Keranjang Masih Kosong'))
                ->body(__('Silakan pilih item pesanan terlebih dahulu.'))
                ->warning()
                ->send();

            return ['ok' => false];
        }

        $productIds = collect($items)->pluck('product_id')->unique()->filter()->all();
        $productsMap = Product::with('variations')->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($items as $item) {
            $product = $productsMap->get($item['product_id'] ?? null);
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

        $orderType = $payload['order_type'] ?? null;
        if (empty($orderType)) {
            $this->notifyMissingOrderType();
            return ['ok' => false];
        }

        $activeDraftId = ! empty($payload['active_draft_id']) ? (int) $payload['active_draft_id'] : null;
        $activeDraft = $activeDraftId ? CartDraft::find($activeDraftId) : null;
        $skipKitchenPrint = (bool) ($activeDraft?->is_kitchen_printed ?? false);

        $paymentMethodId = $payload['payment_method_id'] ?? ($this->paymentMethods[0]['id'] ?? 1);
        $pMethod = PaymentMethod::find($paymentMethodId);
        if (! $pMethod) {
            $pMethod = PaymentMethod::create([
                'name' => 'Cash',
                'is_cash' => true,
                'is_debit' => false,
                'is_credit' => false,
                'is_wallet' => false,
                'icon' => 'assets/images/payment-methods/cash.png',
            ]);
        }

        $mappedProducts = collect($items)->map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'qty' => (float) ($item['qty'] ?? 1),
                'price' => (float) ($item['price'] ?? 0),
                'discount_price' => (float) ($item['discount_price'] ?? 0),
                'price_unit_id' => $item['price_unit_id'] ?? null,
                'selected_variations' => $item['selected_variations'] ?? [],
                'note' => $item['note'] ?? null,
            ];
        })->values()->all();

        $totalPrice = (float) ($payload['total_price'] ?? 0);
        $totalQty = collect($mappedProducts)->sum('qty');
        $payedMoney = (float) ($payload['payed_money'] ?? 0);

        $request = [
            'payment_method_id' => $pMethod->id,
            'member_id' => $payload['member_id'] ?? null,
            'table_id' => $payload['table_id'] ?? null,
            'voucher' => $payload['voucher'] ?? null,
            'discount_price' => (float) ($payload['discount_price'] ?? 0),
            'note' => $payload['note'] ?? null,
            'due_date' => $payload['due_date'] ?? null,
            'payed_money' => $payedMoney,
            'money_changes' => $payedMoney - $totalPrice,
            'friend_price' => (bool) ($payload['friend_price'] ?? false),
            'total_price' => $totalPrice,
            'total_qty' => $totalQty,
            'order_type' => $orderType,
            'skip_kitchen_print' => $skipKitchenPrint,
            'products' => $mappedProducts,
        ];

        $validator = Validator::make($request, [
            'fee' => ['numeric'],
            'payment_method_id' => ['required'],
            'member_id' => Rule::requiredIf(fn () => $pMethod->is_credit),
            'due_date' => Rule::requiredIf(fn () => $pMethod->is_credit),
            'payed_money' => [
                ! $pMethod->is_credit ? 'gte:total_price' : null,
                Rule::requiredIf(fn () => ! $pMethod->is_credit),
            ],
            'total_price' => ['required_if:friend_price,true', 'numeric'],
            'total_qty' => ['required_if:friend_price,true', 'numeric', new ShouldSameWithSellingDetail('qty', $request['products'])],
            'friend_price' => ['required', 'boolean'],
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.price' => ['required_if:friend_price,true', 'numeric'],
            'products.*.qty' => ['required', 'numeric', 'min:1', new CheckProductStock],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->messages()->toArray());
        }

        $data = array_merge($request, $sellingService->mapProductRequest($request));
        $selling = $sellingService->create($data);

        CartItem::query()->cashier()->delete();

        if ($activeDraftId) {
            CartDraft::whereKey($activeDraftId)->delete();
        }

        $this->activeDraftId = null;
        $this->draftName = '';
        $this->orderType = null;
        $this->cartDetail = [];

        Notification::make()
            ->title(__('Transaction created'))
            ->success()
            ->send();

        $this->refreshPosProducts();
        $this->refreshPosDrafts();

        $loadedSelling = $selling->load('sellingDetails.product.category', 'paymentMethod', 'member', 'table', 'user');

        $this->dispatch('selling-created', selling: $loadedSelling, skipKitchenPrint: $skipKitchenPrint);

        return [
            'ok' => true,
            'selling' => $loadedSelling->toArray(),
            'skip_kitchen_print' => $skipKitchenPrint,
            'products' => $this->posProducts,
            'drafts' => $this->posDrafts,
        ];
    }

    public function assignVoucher(string $code)
    {
        $this->validateVoucher($code) ? $this->cartDetail['voucher'] = $code : null;
    }

    public function removeVoucher()
    {
        $this->cartDetail['voucher'] = null;
        $this->discount_price = 0;
        $this->calculateTotalPrice();
    }

    private function validateVoucher(string $code): bool
    {
        $voucherService = new VoucherService();
        $voucher = $voucherService->applyable($code, $this->total_price);
        if (! $voucher) {
            Notification::make('voucher_not_found')
                ->title(__('Voucher not found'))
                ->warning()
                ->send();

            return false;
        }

        $this->cartItems->each(function (CartItem $item) {
            if ($item->discount_price && $item->discount_price > 0) {
                $this->discount_price += $item->discount_price;
            }
        });
        $this->discount_price += $voucher->calculate();
        $this->total_price = $this->sub_total + ($this->sub_total * $this->tax / 100) - $this->discount_price;

        return true;
    }

    private function calculateTotalPrice()
    {
        $this->sub_total = 0;

        $this->discount_price = 0;
        $this->cartItems->each(function (CartItem $item) {
            $priceUnit = $item->priceUnit?->selling_price;
            if ($priceUnit) {
                $priceUnit = $priceUnit * $item->qty;
            }

            $this->sub_total += $priceUnit ?? $item->price;
            if ($item->discount_price && $item->discount_price > 0) {
                $this->discount_price += $item->discount_price;
            }
        });

        $this->total_price = $this->sub_total + ($this->sub_total * $this->tax / 100) - $this->discount_price;
    }
}
