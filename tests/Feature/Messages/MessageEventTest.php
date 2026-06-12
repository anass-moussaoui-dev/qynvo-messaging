<?php

namespace Tests\Feature\Messages;

use App\Events\MessageSent;
use App\Listeners\SendMessageNotification;
use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Integration tests for the event side of POST /api/messages.
 *
 * Proves the whole chain, not just the dispatch call:
 *  - MessageSent is dispatched carrying the exact persisted message
 *  - SendMessageNotification is wired to MessageSent (Laravel event
 *    auto-discovery — there is no manual registration to fall back on)
 *  - the listener is pushed to the queue, because it implements ShouldQueue
 *    (asserted with Queue::fake, which works even though tests run the sync
 *    queue driver)
 *  - rejected requests (validation failure, authorization failure) dispatch
 *    nothing and queue nothing
 */
class MessageEventTest extends TestCase
{
    use RefreshDatabase;

    private User $traveller;

    private Itinerary $itinerary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->traveller = User::factory()->traveller()->create();

        $this->itinerary = Itinerary::factory()->create([
            'traveller_id' => $this->traveller->id,
        ]);
    }

    private function send(array $payload, ?User $actor = null)
    {
        return $this->postJson(
            '/api/messages',
            $payload,
            ['X-User-Id' => (string) ($actor ?? $this->traveller)->id]
        );
    }

    private function validPayload(): array
    {
        return [
            'itinerary_id' => $this->itinerary->id,
            'content' => 'Is the city tour still on for the afternoon?',
        ];
    }

    public function test_message_sent_event_is_dispatched_with_the_created_message(): void
    {
        Event::fake([MessageSent::class]);

        $response = $this->send($this->validPayload())->assertCreated();

        Event::assertDispatched(
            MessageSent::class,
            fn (MessageSent $event): bool => $event->message->id === $response->json('data.id')
                && $event->message->content === 'Is the city tour still on for the afternoon?'
        );
        Event::assertDispatchedTimes(MessageSent::class, 1);
    }

    public function test_notification_listener_is_registered_for_the_event(): void
    {
        Event::fake();

        // Fails if event auto-discovery stops picking the listener up (e.g.
        // after a rename or a move out of app/Listeners).
        Event::assertListening(MessageSent::class, SendMessageNotification::class);
    }

    public function test_notification_listener_is_pushed_to_the_queue(): void
    {
        // Events stay real here; only the queue is faked. Because the
        // listener implements ShouldQueue, dispatching the event must enqueue
        // a CallQueuedListener job wrapping it.
        Queue::fake();

        $this->send($this->validPayload())->assertCreated();

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === SendMessageNotification::class
        );
    }

    public function test_nothing_is_dispatched_when_validation_fails(): void
    {
        Event::fake([MessageSent::class]);

        $this->send(['itinerary_id' => $this->itinerary->id, 'content' => ''])
            ->assertUnprocessable();

        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_nothing_is_dispatched_or_queued_when_authorization_fails(): void
    {
        $outsider = User::factory()->create();

        Event::fake([MessageSent::class]);
        Queue::fake();

        $this->send($this->validPayload(), $outsider)->assertForbidden();

        Event::assertNotDispatched(MessageSent::class);
        Queue::assertNothingPushed();
    }
}
