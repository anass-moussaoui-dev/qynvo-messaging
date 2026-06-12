<?php

namespace App\Listeners;

use App\Events\MessageSent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Queued reaction to MessageSent: records a structured notification entry.
 *
 * Stands in for a real notification channel (mail, push, broadcast) — the
 * queue wiring is identical, only the side effect would change.
 */
class SendMessageNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        Log::info('Message notification dispatched', [
            'message_id' => $event->message->id,
            'itinerary_id' => $event->message->itinerary_id,
            'sender_type' => $event->message->sender_type->value,
        ]);
    }
}
