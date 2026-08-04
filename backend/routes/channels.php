<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('businesses.{businessId}', fn ($user, $businessId) => (int) $user->business_id === (int) $businessId);
Broadcast::channel('notifications.user.{userId}', fn ($user, $userId) => (int) $user->id === (int) $userId);
Broadcast::channel('notifications.customer.{customerId}', fn ($customer, $customerId) => class_basename($customer) === 'Customer' && (int) $customer->id === (int) $customerId);
