<?php

namespace App\Filament\Tenant\Resources;

use App\Features\Role;
use App\Filament\Tenant\Resources\UserResource\Pages;
use App\Models\Tenants\User;
use App\Traits\HasTranslatableResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    use HasTranslatableResource;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email')
                    ->unique(ignoreRecord: true)
                    ->email()
                    ->required(),
                TextInput::make('profile.phone')
                    ->label('Phone'),
                TextInput::make('profile.address')
                    ->label('Address'),
                TextInput::make('password')
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->autocomplete('new-password')
                    ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                    ->extraInputAttributes(fn (string $operation): array => $operation === 'edit'
                        ? ['autocomplete' => 'new-password', 'readonly' => true, 'onfocus' => "this.removeAttribute('readonly')"]
                        : ['autocomplete' => 'new-password'])
                    ->live()
                    ->nullable()
                    ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->same('password_confirmation')
                    ->placeholder(fn (string $operation): string => $operation === 'edit'
                        ? __('Isi jika ingin mengganti password')
                        : '')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? __('Biarkan kosong jika tidak ingin mengganti password.')
                        : null),
                TextInput::make('password_confirmation')
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->autocomplete('new-password')
                    ->label('Confirm New Password')
                    ->dehydrated(false)
                    ->required(fn (string $operation, Get $get): bool => $operation === 'create' || filled($get('password')))
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' || filled($get('password'))),
                TextInput::make('pin')
                    ->label(__('PIN (POS / Admin Panel)'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->autocomplete('new-password')
                    ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                    ->extraInputAttributes(fn (string $operation): array => $operation === 'edit'
                        ? ['autocomplete' => 'new-password', 'readonly' => true, 'onfocus' => "this.removeAttribute('readonly')"]
                        : ['autocomplete' => 'new-password'])
                    ->numeric()
                    ->minLength(4)
                    ->maxLength(8)
                    ->required(fn (string $operation, ?User $record): bool => $operation === 'create' || ! ($record?->hasPin() ?? false))
                    ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->placeholder(fn (string $operation, ?User $record): string => ($operation === 'create' || ! ($record?->hasPin() ?? false))
                        ? __('Masukkan 4-8 digit PIN')
                        : __('Kosongkan jika tidak ingin mengubah PIN'))
                    ->helperText(__('Wajib 4-8 digit angka. Digunakan untuk masuk ke halaman POS atau membuka Admin Panel.')),
                Select::make('roles')
                    ->label('Roles')
                    ->default(1)
                    ->visible(hasFeatureAndPermission(Role::class))
                    ->relationship('roles', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return User::query();
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('profile.phone')
                    ->searchable()
                    ->label('Phone'),
                TextColumn::make('profile.address')
                    ->label('Address'),
                TextColumn::make('roles.0.name')
                    ->visible(hasFeatureAndPermission(Role::class))
                    ->label('Role'),
                IconColumn::make('has_pin')
                    ->label(__('PIN'))
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->hasPin()),
                BooleanColumn::make('is_owner'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->id === auth()->id()),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
