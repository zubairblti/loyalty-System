<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $guarded = [];

    protected $casts = ['features' => 'array', 'active' => 'boolean'];

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }
}
