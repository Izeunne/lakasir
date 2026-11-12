<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Pages\Traits\HasReportPageSidebar;
use App\Traits\HasTranslatableResource;
use Filament\Pages\Page;

class Report extends Page
{
    use HasReportPageSidebar, HasTranslatableResource;

    protected static ?string $title = '';

    public static ?string $label = 'Report';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.tenant.pages.report';

    public static function canAccess(): bool
    {
        return can('access report');
    }

    public function mount(): void
    {
        if (can('generate selling report')) {
            $this->redirect(SellingReport::getUrl());
        } elseif (can('generate product report')) {
            $this->redirect(ProductReport::getUrl());
        } elseif (can('generate cashier report')) {
            $this->redirect(CashierReport::getUrl());
        } elseif (can('generate purchasing report')) {
            $this->redirect(PurchasingReport::getUrl());
        }
    }
}
