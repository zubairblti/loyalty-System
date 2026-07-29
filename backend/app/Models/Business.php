<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $guarded = [];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
