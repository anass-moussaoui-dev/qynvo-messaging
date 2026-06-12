<?php

namespace Tests\Feature\Messages;

use App\Models\Itinerary;
use App\Models\Message;
use App\Models\User;
use App\Services\SuggestsAgencyReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\Support\FakeAnthropic;
use Tests\TestCase;

/**
 * Tests for the AI-suggested agency reply on POST /api/messages.
 *
 * Two layers, deliberately separate:
 *  - Integration: the real SuggestsAgencyReply service against a faked
 *    Anthropic API (FakeAnthropic) — request shape, headers, conversation
 *    mapping, retry policy, and every graceful-degradation path.
 *  - Wiring: the service swapped for a container mock — proves the
 *    controller calls it exactly when it should (traveller yes, agency no)
 *    independently of the HTTP layer.
 *
 * The suite never reaches the real API: phpunit.xml force-empties
 * ANTHROPIC_API_KEY and the base TestCase enables preventStrayRequests().
 */
class SuggestedReplyTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_KEY = 'sk-ant-test-fake-key';

    private User $traveller;

    private User $agency;

    private Itinerary $itinerary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->traveller = User::factory()->traveller()->create();
        $this->agency = User::factory()->agency()->create();

        $this->itinerary = Itinerary::factory()->create([
            'traveller_id' => $this->traveller->id,
            'agency_id' => $this->agency->id,
        ]);

        // Most tests exercise the "key configured" path; the missing-key
        // test overrides this back to null.
        config(['services.anthropic.key' => self::FAKE_KEY]);
    }

    private function sendAs(User $sender, string $content = 'What time is the airport pickup?')
    {
        return $this->postJson('/api/messages', [
            'itinerary_id' => $this->itinerary->id,
            'content' => $content,
        ], ['X-User-Id' => (string) $sender->id]);
    }

    /**
     * Create a historical message on the shared itinerary.
     */
    private function priorMessage(User $sender, string $content, string $createdAt): Message
    {
        return Message::factory()
            ->for($this->itinerary)
            ->from($sender)
            ->create(['content' => $content, 'created_at' => Carbon::parse($createdAt, 'UTC')]);
    }

    // ──────────────────────────────────── integration: request contract

    public function test_traveller_message_returns_a_suggested_reply(): void
    {
        FakeAnthropic::reply('Your driver is confirmed for 9:00 AM at the lobby.');

        $this->sendAs($this->traveller)
            ->assertCreated()
            ->assertJsonPath('suggested_reply', 'Your driver is confirmed for 9:00 AM at the lobby.');

        Http::assertSent(function (Request $request): bool {
            $lastTurn = collect($request['messages'])->last();

            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', self::FAKE_KEY)
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $request['model'] === 'claude-haiku-4-5'
                && $request['max_tokens'] === 150
                && filled($request['system'])
                && $lastTurn['role'] === 'user'
                && str_contains($lastTurn['content'], 'What time is the airport pickup?');
        });
    }

    public function test_conversation_history_is_sent_with_mapped_roles(): void
    {
        $this->priorMessage($this->traveller, 'Is breakfast included?', '2026-06-12 09:00:00');
        $this->priorMessage($this->agency, 'Yes, breakfast is included daily.', '2026-06-12 09:05:00');

        FakeAnthropic::reply('Happy to help!');

        $this->sendAs($this->traveller, 'Great, and what about dinner?')->assertCreated();

        // Traveller speaks as `user`, agency as `assistant`, oldest first,
        // with the new message as the final user turn.
        Http::assertSent(function (Request $request): bool {
            $turns = $request['messages'];

            return array_column($turns, 'role') === ['user', 'assistant', 'user']
                && str_contains($turns[0]['content'], 'Is breakfast included?')
                && str_contains($turns[1]['content'], 'Yes, breakfast is included daily.')
                && str_contains($turns[2]['content'], 'Great, and what about dinner?');
        });
    }

    public function test_history_is_normalized_for_the_api(): void
    {
        // The API requires the first turn to be `user` — a leading agency
        // message must be dropped — and consecutive same-role messages must
        // be coalesced into one turn.
        $this->priorMessage($this->agency, 'Welcome aboard!', '2026-06-12 08:00:00');
        $this->priorMessage($this->traveller, 'First question about the hotel.', '2026-06-12 09:00:00');
        $this->priorMessage($this->traveller, 'Second question about the tour.', '2026-06-12 09:01:00');

        FakeAnthropic::reply('Of course!');

        $this->sendAs($this->traveller, 'Third question about the transfer.')->assertCreated();

        Http::assertSent(function (Request $request): bool {
            $turns = $request['messages'];

            // All three traveller messages coalesce into a single user turn;
            // content checks pin behavior without being brittle about the
            // separator formatting.
            return count($turns) === 1
                && $turns[0]['role'] === 'user'
                && str_contains($turns[0]['content'], 'First question about the hotel.')
                && str_contains($turns[0]['content'], 'Second question about the tour.')
                && str_contains($turns[0]['content'], 'Third question about the transfer.')
                && ! str_contains($turns[0]['content'], 'Welcome aboard!');
        });
    }

    public function test_agency_message_gets_a_null_suggestion_without_calling_the_api(): void
    {
        Http::fake(); // nothing should be sent at all

        $this->sendAs($this->agency, 'Your tour is confirmed.')
            ->assertCreated()
            ->assertJsonPath('suggested_reply', null);

        Http::assertNothingSent();
    }

    // ─────────────────────────────── integration: graceful degradation

    public function test_message_is_still_created_when_the_api_errors(): void
    {
        FakeAnthropic::error(500);

        $this->sendAs($this->traveller)
            ->assertCreated()
            ->assertJsonPath('suggested_reply', null);

        $this->assertDatabaseCount('messages', 1);
        Http::assertSentCount(2); // one retry, then degraded — never blocked the send
    }

    public function test_message_is_still_created_when_the_api_times_out(): void
    {
        FakeAnthropic::timeout();

        $this->sendAs($this->traveller)
            ->assertCreated()
            ->assertJsonPath('suggested_reply', null);

        $this->assertDatabaseCount('messages', 1);

        // Connection failures pass the retry gate: one retry before degrading.
        $this->assertSame(2, FakeAnthropic::$attempts);
    }

    public function test_message_is_still_created_without_an_api_key(): void
    {
        config(['services.anthropic.key' => null]);
        Http::fake();

        $this->sendAs($this->traveller)
            ->assertCreated()
            ->assertJsonPath('suggested_reply', null);

        $this->assertDatabaseCount('messages', 1);
        Http::assertNothingSent(); // short-circuits before any HTTP
    }

    // ──────────────────────────────────────── integration: retry policy

    public function test_transient_error_is_retried_once_and_recovers(): void
    {
        FakeAnthropic::errorThenReply(500, 'Recovered suggestion.');

        $this->sendAs($this->traveller)
            ->assertCreated()
            ->assertJsonPath('suggested_reply', 'Recovered suggestion.');

        Http::assertSentCount(2);
    }

    public function test_deterministic_client_error_is_not_retried(): void
    {
        // A 400 will fail identically every time — retrying it would only
        // add latency to the traveller's request.
        FakeAnthropic::error(400);

        $this->sendAs($this->traveller)
            ->assertCreated()
            ->assertJsonPath('suggested_reply', null);

        Http::assertSentCount(1);
    }

    // ─────────────────────────────────────────── integration: security

    public function test_api_key_is_never_exposed_in_the_response(): void
    {
        FakeAnthropic::reply('Sure thing!');

        $response = $this->sendAs($this->traveller)->assertCreated();

        $this->assertStringNotContainsString(self::FAKE_KEY, $response->getContent());

        // The POST response carries exactly the resource and the suggestion —
        // nothing else can leak.
        $this->assertSame(['data', 'suggested_reply'], array_keys($response->json()));
    }

    public function test_list_endpoint_is_unchanged(): void
    {
        Message::factory()->for($this->itinerary)->from($this->traveller)->create();

        $this->getJson("/api/messages/{$this->itinerary->id}", ['X-User-Id' => (string) $this->agency->id])
            ->assertOk()
            ->assertJsonMissingPath('suggested_reply');
    }

    // ───────────────────────────────────── wiring: controller ↔ service

    public function test_controller_passes_the_persisted_message_to_the_service(): void
    {
        $this->mock(SuggestsAgencyReply::class, function (MockInterface $mock): void {
            $mock->shouldReceive('suggest')
                ->once()
                ->withArgs(fn (Message $message): bool => $message->exists
                    && $message->content === 'Wiring check'
                    && $message->sender_id === $this->traveller->id)
                ->andReturn('Mocked suggestion');
        });

        $this->sendAs($this->traveller, 'Wiring check')
            ->assertCreated()
            ->assertJsonPath('suggested_reply', 'Mocked suggestion');
    }

    public function test_controller_never_calls_the_service_for_agency_senders(): void
    {
        $this->mock(SuggestsAgencyReply::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('suggest');
        });

        $this->sendAs($this->agency, 'No suggestion needed')
            ->assertCreated()
            ->assertJsonPath('suggested_reply', null);
    }
}
