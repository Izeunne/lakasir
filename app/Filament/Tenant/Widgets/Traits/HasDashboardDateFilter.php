<?php

namespace App\Filament\Tenant\Widgets\Traits;

use App\Models\Tenants\Profile;
use Illuminate\Support\Carbon;

trait HasDashboardDateFilter
{
    protected function getFilterDateRange(): array
    {
        $timezone = Profile::get()->timezone ?: config('app.timezone', 'UTC');
        $now = now($timezone);

        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        if ($startDate) {
            $start = Carbon::parse($startDate, $timezone)->startOfDay();
        } else {
            $start = $now->copy()->startOfDay();
        }

        if ($endDate) {
            $end = Carbon::parse($endDate, $timezone)->endOfDay();
        } else {
            $end = $now->copy()->endOfDay();
        }

        $diffInDays = max(1, (int) $start->diffInDays($end) + 1);
        $prevStart = $start->copy()->subDays($diffInDays);
        $prevEnd = $start->copy()->subSecond();

        return [
            'start' => $start,
            'end' => $end,
            'startUtc' => $start->copy()->setTimezone('UTC'),
            'endUtc' => $end->copy()->setTimezone('UTC'),
            'prevStart' => $prevStart,
            'prevEnd' => $prevEnd,
            'prevStartUtc' => $prevStart->copy()->setTimezone('UTC'),
            'prevEndUtc' => $prevEnd->copy()->setTimezone('UTC'),
            'diffInDays' => $diffInDays,
            'isToday' => $start->isToday() && $end->isToday(),
            'timezone' => $timezone,
        ];
    }
}

