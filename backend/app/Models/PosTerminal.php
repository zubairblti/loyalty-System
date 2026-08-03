<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class PosTerminal extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function secret(): Attribute
    {
        return Attribute::make(get: fn ($v) => decrypt($v), set: fn ($v) => encrypt($v));
    }
}
