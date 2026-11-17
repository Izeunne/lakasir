<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenants\Profile;
use App\Models\Tenants\Selling;
use App\Models\Tenants\Setting;
use App\Traits\HasTranslatableResource;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class VoidedSellings extends Page implements HasTable
{
    use HasTranslatableResource, InteractsWithTable;

    public static ?string $label = 'Riwayat Void';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static string $view = 'filament.tenant.pages.voided-sellings';

    public static function canAccess(): bool
    {
        return can('read selling') || (auth()->user()?->canManageStorePin() ?? false);
    }

    public function table(Table $table): Table
    {
        $timezone = Profile::get()->timezone ?? config('app.timezone');

        return $table
            ->query(
                Selling::onlyTrashed()
                    ->with(['user', 'member', 'paymentMethod', 'voidedBy', 'sellingDetails.product'])
            )
            ->defaultSort('deleted_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label(__('Tanggal Transaksi'))
                    ->dateTime(timezone: $timezone)
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('Kasir'))
                    ->searchable(),
                TextColumn::make('total_price')
                    ->label(__('Total'))
                    ->money(Setting::get('currency', 'IDR'))
                    ->sortable(),
                TextColumn::make('void_reason')
                    ->label(__('Alasan Void'))
                    ->placeholder('-')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('voidedBy.name')
                    ->label(__('Di-void Oleh'))
                    ->placeholder('-'),
                TextColumn::make('deleted_at')
                    ->label(__('Waktu Void'))
                    ->dateTime(timezone: $timezone)
                    ->sortable(),
            ])
            ->actions([
                Action::make('detail')
                    ->label(__('Detail'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Selling $record): string => __('Detail Transaksi Void: :code', ['code' => $record->code]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Tutup'))
                    ->modalContent(fn (Selling $record) => view('filament.tenant.pages.voided-selling-detail', [
                        'selling' => $record,
                    ])),
            ])
            ->emptyStateHeading(__('Belum ada data void'))
            ->emptyStateDescription(__('Transaksi penjualan yang di-void akan muncul di sini beserta alasannya.'));
    }
}
