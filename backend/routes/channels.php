<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('businesses.{businessId}', fn ($user, $businessId) => (int) $user->business_id === (int) $businessId);
