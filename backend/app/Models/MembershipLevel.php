<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MembershipLevel extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['benefits' => 'array', 'active' => 'boolean'];
}
