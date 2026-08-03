<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['verified_at' => 'datetime'];
}
