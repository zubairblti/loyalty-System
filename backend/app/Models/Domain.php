<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $guarded = [];

    protected $casts = ['verified_at' => 'datetime'];
}
