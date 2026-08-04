<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'profile_completed_at' => 'datetime',
    ];

    protected $appends = ['profile_completed'];

    public function getProfileCompletedAttribute(): bool
    {
        return $this->profile_completed_at !== null;
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->where('ends_at', '>', now())->latestOfMany();
    }

    public function owner()
    {
        return $this->hasOne(User::class)->where('role', 'owner');
    }

    public function loyaltySetting()
    {
        return $this->hasOne(LoyaltySetting::class);
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class);
    }

    public function integrations()
    {
        return $this->hasMany(Integration::class);
    }
}
