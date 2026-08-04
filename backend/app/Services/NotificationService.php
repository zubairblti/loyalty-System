<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Notifications\CrmNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function send(Model $recipient, string $type, string $title, string $message, ?string $actionUrl = null, ?string $deduplicationKey = null): ?DatabaseNotification
    {
        if ($deduplicationKey && DB::table('notifications')->where('notifiable_type', $recipient->getMorphClass())
            ->where('notifiable_id', $recipient->getKey())->where('data', 'like', '%"deduplication_key":"'.$deduplicationKey.'"%')->exists()) {
            return null;
        }

        $recipient->notifyNow(new CrmNotification(array_filter([
            'type' => $type, 'title' => $title, 'message' => $message,
            'action_url' => $actionUrl, 'deduplication_key' => $deduplicationKey,
        ], fn ($value) => $value !== null)));
        $notification = $recipient->notifications()->latest()->first();
        if (! $notification) {
            return null;
        }
        $audience = class_basename($recipient) === 'Customer' ? 'customer' : 'user';
        NotificationCreated::dispatch($audience, (int) $recipient->getKey(), $notification);

        return $notification;
    }
}
