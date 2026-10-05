<?php

namespace App\Events;

use App\Models\GlobalChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GlobalChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $companyId, public int $messageId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('global-chat.company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'global-chat.message-created';
    }

    public function broadcastWith(): array
    {
        return ['messageId' => $this->messageId];
    }

    public static function fromMessage(GlobalChatMessage $message): self
    {
        return new self(
            (int) $message->permit_company_id,
            (int) $message->getKey(),
        );
    }
}
