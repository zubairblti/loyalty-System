<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class QrCode extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime', 'claimed_at' => 'datetime'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
