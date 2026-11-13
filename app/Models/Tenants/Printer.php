<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPrinter
 */
class Printer extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'print_all_categories' => 'boolean',
        'category_ids' => 'array',
        'show_price' => 'boolean',
        'show_header' => 'boolean',
        'copies' => 'integer',
        'is_active' => 'boolean',
    ];

    public function mobilePrinter(): BelongsTo
    {
        return $this->belongsTo(MobilePrinter::class, 'device_id', 'device_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function appliesToCategory(?int $categoryId): bool
    {
        if ($this->print_all_categories) {
            return true;
        }

        if ($categoryId === null) {
            return false;
        }

        $ids = array_map('intval', $this->category_ids ?? []);

        return in_array((int) $categoryId, $ids, true);
    }

    public function getCategoryNamesAttribute(): array
    {
        if ($this->print_all_categories) {
            return [__('Semua Kategori')];
        }

        $ids = array_filter(array_map('intval', $this->category_ids ?? []));
        if (empty($ids)) {
            return [];
        }

        return Category::whereIn('id', $ids)->pluck('name')->toArray();
    }
}
