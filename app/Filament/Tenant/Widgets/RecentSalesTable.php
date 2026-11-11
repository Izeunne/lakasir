<?php

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Widgets\Traits\HasDashboardDateFilter;
use App\Models\Tenants\Selling;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentSalesTable extends BaseWidget
{
    use HasDashboardDateFilter, InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return can('read selling');
    }

    public function getTableHeading(): ?string
    {
        return __('Recent Transactions');
    }

    public function table(Table $table): Table
    {
        $dates = $this->getFilterDateRange();
        $startUtc = $dates['startUtc'];
        $endUtc = $dates['endUtc'];

        $query = Selling::query()
            ->with(['user', 'member', 'paymentMethod', 'table'])
            ->whereBetween('date', [$startUtc, $endUtc])
            ->latest('date')
            ->limit(10);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('code')
                    ->label(__('Invoice'))
                    ->weight('bold')
                    ->copyable()
                    ->url(fn (Selling $record) => '/member/sellings/' . $record->id),
                TextColumn::make('date')
                    ->label(__('Date'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(false),
                TextColumn::make('user.name')
                    ->label(__('Cashier'))
                    ->default('-'),
                TextColumn::make('member.name')
                    ->label(__('Member'))
                    ->default(fn (Selling $record) => $record->table ? __('Table') . ' ' . $record->table->number : '-'),
                TextColumn::make('paymentMethod.name')
                    ->label(__('Payment Method'))
                    ->badge()
                    ->color('info')
                    ->default(__('Cash')),
                TextColumn::make('total_price')
                    ->label(__('Total'))
                    ->alignEnd()
                    ->weight('semibold')
                    ->formatStateUsing(function (Selling $record) {
                        $net = $record->total_price - ($record->discount_price ?? 0) - ($record->total_discount_per_item ?? 0);
                        return price_format((float) $net);
                    }),
                TextColumn::make('is_paid')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? __('Paid off') : __('Unpaid')),
            ])
            ->actions([
                Action::make('view')
                    ->label(__('Details'))
                    ->icon('heroicon-m-eye')
                    ->url(fn (Selling $record) => '/member/sellings/' . $record->id),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No transactions found'))
            ->emptyStateDescription(__('No transactions recorded in the selected period.'));
    }
}

