<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenants\Setting;
use App\Traits\HasTranslatableResource;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

class Printer extends Page implements HasActions, HasForms
{
    use HasTranslatableResource;
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-printer';

    protected static string $view = 'filament.tenant.pages.printer';

    public static function canAccess(): bool
    {
        return can('access printer');
    }

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill([
            'driver' => Setting::get('printer_driver', 'usb'),
            'paper_width' => Setting::get('printer_paper_width', '58mm'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Components\Textarea::make('header')
                ->rows(5)
                ->translateLabel(),
            Components\TextInput::make('name')
                ->required()
                ->translateLabel(),
            Components\Select::make('driver')
                ->default('usb')
                ->options([
                    'usb' => 'USB (Printer Fisik)',
                    'virtual' => 'Virtual / Simulator (Preview Layar - Tanpa Printer Fisik)',
                ])
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state === 'virtual') {
                        $set('name', 'Virtual Thermal Simulator');
                        $set('printer', 'Virtual Printer (Preview Layar)');
                        $set('printerId', 'virtual_simulator');
                    }
                })
                ->translateLabel(),
            Components\Select::make('paper_width')
                ->label(__('Ukuran Kertas'))
                ->helperText(__('Pilih ukuran lebar kertas thermal printer'))
                ->default('58mm')
                ->options([
                    '58mm' => '58 mm (Standar)',
                    '80mm' => '80 mm (Lebar)',
                ])
                ->required(),
            Grid::make(columns: 3)
                ->schema([
                    Components\TextInput::make('printer')
                        ->required()
                        ->helperText(fn (callable $get) => $get('driver') === 'virtual'
                            ? __('Mode simulator aktif: hasil cetak struk akan ditampilkan langsung di layar.')
                            : __('Please click the select printer button to choose the connected printer'))
                        ->readOnly()
                        ->translateLabel()
                        ->columnSpan(2),
                    Components\TextInput::make('printerId')
                        ->required()
                        ->hintActions([
                            ActionsAction::make('select_printer')
                                ->icon('heroicon-o-printer')
                                ->translateLabel()
                                ->visible(fn (callable $get) => $get('driver') !== 'virtual')
                                ->extraAttributes([
                                    'x-on:click' => 'fetchDeviceByDriver',
                                ]),
                        ])
                        ->readOnly(),
                ]),
            Components\Textarea::make('footer')
                ->rows(5)
                ->translateLabel(),
        ])->statePath('data');
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->translateLabel()
                ->extraAttributes([
                    'x-on:click' => 'save',
                ]),
            Action::make('test')
                ->translateLabel()
                ->color('warning')
                ->icon('heroicon-o-printer')
                ->extraAttributes([
                    'x-on:click' => 'test',
                ]),
        ];
    }

    public function saveSettings(array $data): void
    {
        if (isset($data['paper_width'])) {
            Setting::set('printer_paper_width', $data['paper_width']);
        }
        if (isset($data['driver'])) {
            Setting::set('printer_driver', $data['driver']);
        }
    }

    public function validateInput()
    {
        $this->validate([
            'data.printer' => 'required',
            'data.name' => 'required',
            'data.paper_width' => 'required',
        ]);
    }
}
