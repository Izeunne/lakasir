<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenants\User;
use App\Traits\HasTranslatableResource;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class StorePin extends Page implements HasTable
{
    use HasTranslatableResource, InteractsWithTable;

    public static ?string $label = 'Pengaturan PIN';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static string $view = 'filament.tenant.pages.store-pin';

    public static function canAccess(): bool
    {
        /** @var ?User $user */
        $user = Filament::auth()->user();

        return $user ? $user->canManageStorePin() : false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()->with('roles')
            )
            ->defaultSort('is_owner', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nama'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable(),
                TextColumn::make('role_label')
                    ->label(__('Role'))
                    ->badge()
                    ->getStateUsing(fn (User $record): string => $record->is_owner ? 'Owner / Superadmin' : ($record->roles->first()?->name ?? 'User'))
                    ->color(fn (User $record): string => $record->isAdminPanelUser() ? 'warning' : 'gray'),
                IconColumn::make('has_pin')
                    ->label(__('Status PIN'))
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->hasPin()),
            ])
            ->actions([
                Action::make('set_pin')
                    ->label(fn (User $record): string => $record->hasPin() ? __('Ubah PIN') : __('Atur PIN'))
                    ->icon('heroicon-o-key')
                    ->color(fn (User $record): string => $record->hasPin() ? 'gray' : 'primary')
                    ->modalHeading(fn (User $record): string => __('Atur PIN: :name', ['name' => $record->name]))
                    ->modalDescription(__('Masukkan 4-8 digit angka PIN untuk akses POS atau Admin Panel.'))
                    ->modalSubmitActionLabel(__('Simpan PIN'))
                    ->form([
                        TextInput::make('pin')
                            ->label(__('PIN Baru'))
                            ->password()
                            ->revealable()
                            ->numeric()
                            ->minLength(4)
                            ->maxLength(8)
                            ->required()
                            ->autocomplete('new-password')
                            ->placeholder(__('Masukkan 4-8 digit angka')),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'pin' => Hash::make($data['pin']),
                        ]);

                        Notification::make()
                            ->title(__('PIN Berhasil Disimpan'))
                            ->body(__('PIN untuk :name telah diperbarui.', ['name' => $record->name]))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
