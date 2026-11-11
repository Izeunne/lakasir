<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobilePrinter extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'outlet_id' => 'integer',
        'is_connected' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('is_connected', true);
    }

    public function getDisplayLabelAttribute(): string
    {
        $type = strtoupper($this->connection_type ?: 'BT');
        $addr = $this->address ? " ({$this->address})" : '';

        return "[{$type}] {$this->device_name}{$addr}";
    }
}

