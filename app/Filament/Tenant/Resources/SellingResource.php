<?php

namespace App\Filament\Tenant\Resources;

use App\Features\ProductInitialPrice;
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
                    ->label(__('Void'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading(__('Void Riwayat Penjualan'))
                    ->modalDescription(__('Masukkan PIN Superadmin / Admin untuk me-void riwayat penjualan ini. Data tidak tampil di riwayat maupun laporan, dan stok produk akan dikembalikan.'))
                    ->modalSubmitActionLabel(__('Verifikasi PIN & Void'))
                    ->visible(function (): bool {
                        /** @var ?User $user */
                        $user = Filament::auth()->user();

                        return $user ? $user->canManageStorePin() : false;
                    })
                    ->form(fn (): array => self::getVoidFormSchema())
                    ->action(function (Selling $record, array $data): void {
                        self::handleVoidAction($record, $data);
                    }),
            ])
            ->deferFilters();
    }

    public static function getVoidFormSchema(): array
    {
        return [
            TextInput::make('pin')
                ->label(__('PIN Superadmin / Admin'))
                ->password()
                ->revealable()
                ->numeric()
                ->required()
                ->placeholder(__('Masukkan PIN Superadmin / Admin')),
            Textarea::make('void_reason')
                ->label(__('Alasan Void (Opsional)'))
                ->placeholder(__('Contoh: Salah input item, pelanggan membatalkan pesanan, dll.'))
                ->rows(3)
                ->maxLength(500),
        ];
    }

    public static function handleVoidAction(Selling $record, array $data): bool
    {
        /** @var ?User $authUser */
        $authUser = Filament::auth()->user();

        if (! User::hasConfiguredAdminPin($authUser)) {
            Notification::make()
                ->title(__('PIN Admin Belum Diatur'))
                ->body(__('Silakan atur PIN Superadmin / Admin terlebih dahulu di menu Pengaturan PIN sebelum melakukan void penjualan.'))
                ->danger()
                ->send();

            return false;
        }

        if (! User::verifyAdminPin($data['pin'] ?? null, $authUser)) {
            Notification::make()
                ->title(__('PIN Salah'))
                ->body(__('PIN Superadmin / Admin yang Anda masukkan tidak sesuai. Void dibatalkan.'))
                ->danger()
                ->send();

            return false;
        }

        self::deleteSellingAndRestoreStock($record, $data['void_reason'] ?? null);

        Notification::make()
            ->title(__('Penjualan Berhasil Di-Void'))
            ->body(__('Transaksi telah dipindahkan ke Riwayat Void dan stok produk telah dikembalikan.'))
            ->success()
            ->send();

        return true;
    }

    public static function deleteSellingAndRestoreStock(Selling $record, ?string $voidReason = null): void
    {
        DB::transaction(function () use ($record, $voidReason) {
            $stockService = app(StockService::class);
            $record->loadMissing('sellingDetails.product');

            foreach ($record->sellingDetails as $detail) {
                if ($detail->product && ! $detail->product->is_non_stock) {
                    $stockService->addStock($detail->product, $detail->qty);
                }
            }

            $record->update([
                'void_reason' => filled($voidReason) ? trim($voidReason) : null,
                'voided_by' => Filament::auth()->id() ?? auth()->id(),
            ]);

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
