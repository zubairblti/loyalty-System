<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LoyaltySetting extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['loyalty_enabled' => 'boolean', 'points_enabled' => 'boolean', 'memberships_enabled' => 'boolean', 'membership_downgrade_grace_days' => 'integer', 'completed_tours' => 'array'];
}
