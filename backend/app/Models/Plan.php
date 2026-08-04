<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['features' => 'array', 'active' => 'boolean', 'public' => 'boolean'];

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }
}
