<?php

namespace Tests\Unit;

use App\Enums\UserType;
use App\Events\MessageSent;
use App\Listeners\SendMessageNotification;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Unit tests for the {@see SendMessageNotification} listener.
 *
 * The listener's only side effect is a structured log entry, so the test
 * mocks the Log facade and asserts the exact channel message and context.
 *
 * Extends the framework TestCase because the Log facade needs the container,
 * but deliberately avoids RefreshDatabase: the Message is built in memory
 * with forceFill(). (Message::factory()->make() would not help here — the
 * factory resolves a nested User/Itinerary factory against the database.)
 */
class SendMessageNotificationTest extends TestCase
{
    public function test_handle_logs_the_notification_with_the_message_context(): void
    {
        $message = (new Message)->forceFill([
            'id' => 42,
            'itinerary_id' => 7,
            'sender_type' => UserType::Agency,
            'content' => 'Your driver will arrive at 9:00 AM.',
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Message notification dispatched', [
                'message_id' => 42,
                'itinerary_id' => 7,
                'sender_type' => 'agency',
            ]);

        (new SendMessageNotification)->handle(new MessageSent($message));
    }

    public function test_event_exposes_the_message_it_was_constructed_with(): void
    {
        $message = (new Message)->forceFill(['id' => 1]);

        $event = new MessageSent($message);

        $this->assertSame($message, $event->message);
    }
}
