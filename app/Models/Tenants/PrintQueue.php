<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintQueue extends Model
{
    use HasFactory;

    protected $table = 'print_queue';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'layout' => 'array',
        'transaction' => 'array',
        'ticket' => 'array',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function selling(): BelongsTo
    {
        return $this->belongsTo(Selling::class);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeForOutlet($query, $outletId)
    {
        if (!empty($outletId)) {
            return $query->where('outlet_id', $outletId);
        }
        return $query;
    }

    public function scopeForQueueType($query, string $type)
    {
        return $query->where('queue_type', $type);
    }
}

