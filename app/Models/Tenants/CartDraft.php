<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartDraft extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'items' => 'array',
    ];

    protected $appends = [
        'order_type_label',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function orderTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->order_type) {
                'dine_in' => 'Dine In',
                'take_away' => 'Take Away',
                'ojek_online' => 'Ojek Online',
                default => '-',
            }
        );
    }
}

