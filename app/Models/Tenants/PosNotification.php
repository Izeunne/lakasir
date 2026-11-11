<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosNotification extends Model
{
    use HasFactory;

    protected $table = 'pos_notifications';

    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'outlet_id' => 'integer',
    ];
}

