<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SafepayWebhookEvent extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'processed_at' => 'datetime'];
}
