<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentSubmission extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['reviewed_at' => 'datetime', 'payment_date' => 'datetime'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }
}
