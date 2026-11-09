<?php

namespace App\Filament\Tenant\Resources;

use App\Features\ProductInitialPrice;
use App\Filament\Tenant\Pages\StorePin;
use App\Filament\Tenant\Resources\SellingResource\Pages;
use App\Models\Tenants\Profile;
use App\Models\Tenants\Selling;
use App\Models\Tenants\Setting;
use App\Models\Tenants\User;
use App\Services\Tenants\StockService;
use App\Traits\HasTranslatableResource;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SellingResource extends Resource
{
    use HasTranslatableResource;

    protected static ?string $model = Selling::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Selling History';

    public static function getBreadcrumb(): string
    {
        return __('Selling History');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_type_label')
                    ->label(__('Kategori'))
                    ->badge(),
                TextColumn::make('user.name')
                    ->label(__('Cashier'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function (Builder $query) use ($search) {
                            return $query
                                ->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('member.name')
                    ->translateLabel()
                    ->default('-'),
                TextColumn::make('customer_number')
                    ->translateLabel()
                    ->default('-'),
                TextColumn::make('note')
                    ->label(__('Note Penjualan'))
                    ->limit(35)
                    ->default('-'),
                TextColumn::make('date')
                    ->dateTime(timezone: Profile::get()->timezone)
                    ->translateLabel(),
                TextColumn::make('grand_total_price')
                    ->translateLabel()
                    ->sortable()
                    ->money(Setting::get('currency', 'IDR')),
                TextColumn::make('total_price')
                    ->translateLabel()
                    ->sortable()
                    ->money(Setting::get('currency', 'IDR')),
                TextColumn::make('tax_price')
                    ->translateLabel()
                    ->sortable()
                    ->visible(feature(ProductInitialPrice::class))
                    ->money(Setting::get('currency', 'IDR')),
                TextColumn::make('total_cost')
                    ->translateLabel()
                    ->sortable()
                    ->visible(feature(ProductInitialPrice::class))
                    ->money(Setting::get('currency', 'IDR')),
            ])
            ->searchPlaceholder('Search (Code, User, Customer Number')
            ->header(view('filament.tenant.resources.sellings.headers.overview', [
                'start_date' => request()->input('tableFilters.date.start_date'),
                'end_date' => request()->input('tableFilters.date.end_date'),
            ]))
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('Cashier'))
                    ->options(User::all()->mapWithKeys(fn (User $user) => [$user->id => $user->cashier_name])),
                Filter::make('date')
                    ->form([
                        DatePicker::make('start_date')
                            ->native(false)
                            ->format('Y-m-d')
                            ->timezone(Profile::get()->timezone)
                            ->date()
                            ->closeOnDateSelection(),
                        DatePicker::make('end_date')
                            ->native(false)
                            ->format('Y-m-d')
                            ->timezone(Profile::get()->timezone)
                            ->date()
                            ->closeOnDateSelection(),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['start_date'] && $data['end_date'])) {
                            return null;
                        }

                        return Carbon::parse($data['start_date'])->toFormattedDateString().' s/d '.Carbon::parse($data['end_date'])->toFormattedDateString();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $startDate = $data['start_date'];
                        $endDate = $data['end_date'];
                        if ($timezone = Profile::get()->timezone) {
                            if (! $startDate && ! $endDate) {
                                return $query;
                            }
                            $startDate = Carbon::parse($startDate, $timezone)->setTimezone('UTC');
                            $endDate = Carbon::parse($endDate, $timezone)->addDay()->setTimezone('UTC');
                        }

                        return $query
                            ->when($startDate && $endDate, fn (Builder $builder) => $builder->whereBetween('date', [$startDate, $endDate]));
                    }),
            ])
            ->actions([
                Action::make('print_pdf_receipt')
                    ->label(__('Struk PDF'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn (Selling $record): string => route('selling.receipt.pdf', ['selling' => $record]))
                    ->openUrlInNewTab(),
                Action::make('edit_note')
                    ->label(__('Note Penjualan'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(function (): bool {
                        /** @var ?User $user */
                        $user = Filament::auth()->user();

                        return $user ? $user->canEditSellingNote() : false;
                    })
                    ->fillForm(fn (Selling $record): array => [
                        'note' => $record->note,
                    ])
                    ->form([
                        Textarea::make('note')
                            ->label(__('Note Penjualan'))
                            ->rows(3)
                            ->placeholder(__('Isi catatan untuk penjualan ini (misal: alasan kendala, nomor ojek online, dll)...')),
                    ])
                    ->action(function (Selling $record, array $data): void {
                        $record->update([
                            'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
                        ]);

                        Notification::make()
                            ->title(__('Note Penjualan Disimpan'))
                            ->success()
                            ->send();
                    }),
                Action::make('delete_with_pin')
                    ->label(__('Hapus'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading(__('Hapus Riwayat Penjualan'))
                    ->modalDescription(__('Masukkan PIN Toko untuk menghapus riwayat penjualan ini. Stok produk pada transaksi ini akan dikembalikan.'))
                    ->modalSubmitActionLabel(__('Verifikasi PIN & Hapus'))
                    ->visible(function (): bool {
                        /** @var ?User $user */
                        $user = Filament::auth()->user();

                        return $user ? $user->canManageStorePin() : false;
                    })
                    ->form([
                        TextInput::make('pin')
                            ->label(__('PIN Toko'))
                            ->password()
                            ->revealable()
                            ->numeric()
                            ->required()
                            ->placeholder(__('Masukkan PIN Toko')),
                    ])
                    ->action(function (Selling $record, array $data): void {
                        if (! StorePin::isPinConfigured()) {
                            Notification::make()
                                ->title(__('PIN Toko Belum Diatur'))
                                ->body(__('Silakan atur PIN Toko terlebih dahulu di menu PIN Toko sebelum menghapus penjualan.'))
                                ->danger()
                                ->send();
                            return;
                        }

                        if (! StorePin::verify($data['pin'] ?? null)) {
                            Notification::make()
                                ->title(__('PIN Toko Salah'))
                                ->body(__('PIN Toko yang Anda masukkan tidak sesuai. Penghapusan dibatalkan.'))
                                ->danger()
                                ->send();
                            return;
                        }

                        self::deleteSellingAndRestoreStock($record);

                        Notification::make()
                            ->title(__('Riwayat Penjualan Dihapus'))
                            ->body(__('Data penjualan berhasil dihapus dan stok produk telah dikembalikan.'))
                            ->success()
                            ->send();
                    }),
            ])
            ->deferFilters();
    }

    public static function deleteSellingAndRestoreStock(Selling $record): void
    {
        DB::transaction(function () use ($record) {
            $stockService = app(StockService::class);
            $record->loadMissing('sellingDetails.product');

            foreach ($record->sellingDetails as $detail) {
                if ($detail->product && ! $detail->product->is_non_stock) {
                    $stockService->addStock($detail->product, $detail->qty);
                }
            }

            $record->sellingDetails()->delete();
            $record->delete();
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellings::route('/'),
            'view' => Pages\ViewSelling::route('/{record}'),
        ];
    }
}
