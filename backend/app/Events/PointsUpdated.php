<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PointsUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $businessId, public int $customerId, public int $balance) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("businesses.{$this->businessId}")];
    }

    public function broadcastAs(): string
    {
        return 'points.updated';
    }
}
