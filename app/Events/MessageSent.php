<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a message has been persisted.
 *
 * SerializesModels matters here: the queued SendMessageNotification listener
 * serializes this event, so the message travels as an identifier and is
 * re-hydrated from the database when the job runs.
 */
class MessageSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {}
}
