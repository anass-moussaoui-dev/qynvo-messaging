<?php

namespace Tests\Feature\Messages;

use App\Enums\UserType;
use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature tests for POST /api/messages.
 *
 * Covers the full HTTP contract of the endpoint:
 *  - happy path (201, exact resource shape, persisted row, derived sender_type)
 *  - actor identification via the X-User-Id header (sender identity can never
 *    be supplied — or spoofed — through the request body)
 *  - authorization (only itinerary participants may post)
 *  - strict validation of every payload field, including boundaries, with
 *    proof that failed requests leave no trace in the database
 *
 * Event/listener behavior for this endpoint lives in MessageEventTest.
 */
class StoreMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $traveller;

    private User $agency;

    private User $outsider;

    private Itinerary $itinerary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->traveller = User::factory()->traveller()->create();
        $this->agency = User::factory()->agency()->create();
        $this->outsider = User::factory()->create();

        $this->itinerary = Itinerary::factory()->create([
            'traveller_id' => $this->traveller->id,
            'agency_id' => $this->agency->id,
        ]);
    }

    /**
     * Request headers identifying the acting user, mirroring the API contract.
     */
    private function asUser(User $user): array
    {
        return ['X-User-Id' => (string) $user->id];
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'itinerary_id' => $this->itinerary->id,
            'content' => 'Hi, what time is the airport pickup tomorrow?',
        ], $overrides);
    }

    // ──────────────────────────────────────────────────────────── happy path

    public function test_traveller_can_send_a_message_to_their_itinerary(): void
    {
        // Freeze time so the created_at assertion below is exact. The resource
        // serializes with toIso8601String(): no microseconds, +00:00 offset.
        $this->travelTo(Carbon::create(2026, 6, 12, 10, 0, 0, 'UTC'));

        $response = $this->postJson('/api/messages', $this->validPayload(), $this->asUser($this->traveller));

        $response
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'itinerary_id' => $this->itinerary->id,
                    'sender_type' => 'traveller',
                    'content' => 'Hi, what time is the airport pickup tomorrow?',
                    'created_at' => '2026-06-12T10:00:00+00:00',
                ],
            ])
            ->assertJsonStructure(['data' => ['id', 'itinerary_id', 'sender_type', 'content', 'created_at']]);

        $this->assertDatabaseHas('messages', [
            'id' => $response->json('data.id'),
            'itinerary_id' => $this->itinerary->id,
            'sender_id' => $this->traveller->id,
            'sender_type' => UserType::Traveller->value,
            'content' => 'Hi, what time is the airport pickup tomorrow?',
        ]);
    }

    public function test_agency_sender_type_is_derived_from_the_user(): void
    {
        $response = $this->postJson('/api/messages', $this->validPayload(), $this->asUser($this->agency));

        $response
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'agency');

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->agency->id,
            'sender_type' => UserType::Agency->value,
        ]);
    }

    public function test_sender_identity_in_the_payload_is_ignored(): void
    {
        // A traveller trying to impersonate someone else through the body:
        // sender_id and sender_type are not validated input, so they must be
        // discarded in favor of the header user and their actual type.
        $response = $this->postJson('/api/messages', $this->validPayload([
            'sender_id' => $this->outsider->id,
            'sender_type' => 'agency',
        ]), $this->asUser($this->traveller));

        $response
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'traveller');

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->traveller->id,
            'sender_type' => UserType::Traveller->value,
        ]);
        $this->assertDatabaseMissing('messages', ['sender_id' => $this->outsider->id]);
    }

    // ─────────────────────────────────────── actor identification & policy

    public function test_non_participant_cannot_send_a_message(): void
    {
        $response = $this->postJson('/api/messages', $this->validPayload(), $this->asUser($this->outsider));

        $response->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    /**
     * A missing or unknown actor is a deliberate 401 (authentication
     * failure), kept distinct from 403 (valid actor, not a participant) and
     * 404 (the resource itself does not exist).
     */
    public function test_request_without_user_header_is_unauthenticated(): void
    {
        $this->postJson('/api/messages', $this->validPayload())->assertUnauthorized();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_request_with_unknown_user_header_is_unauthenticated(): void
    {
        $this->postJson('/api/messages', $this->validPayload(), ['X-User-Id' => '999999'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('messages', 0);
    }

    // ──────────────────────────────────────────────────────────── validation

    /**
     * Each invalid payload must be rejected with 422, report the offending
     * field, and leave the messages table untouched.
     *
     * @param  array<string, mixed>  $overrides  invalid fields merged into an otherwise valid payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_invalid_payload_is_rejected(array $overrides, string $expectedErrorField): void
    {
        $response = $this->postJson('/api/messages', $this->validPayload($overrides), $this->asUser($this->traveller));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$expectedErrorField]);

        $this->assertDatabaseCount('messages', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'itinerary_id missing' => [['itinerary_id' => null], 'itinerary_id'],
            'itinerary_id not an integer' => [['itinerary_id' => 'abc'], 'itinerary_id'],
            'itinerary_id not in database' => [['itinerary_id' => 999999], 'itinerary_id'],
            'content missing' => [['content' => null], 'content'],
            'content empty string' => [['content' => ''], 'content'],
            // Trimmed to '' by TrimStrings, then converted to null by
            // ConvertEmptyStringsToNull, so `required` rejects it.
            'content whitespace only' => [['content' => "   \t  "], 'content'],
            'content not a string' => [['content' => ['an', 'array']], 'content'],
            'content longer than 5000' => [['content' => str_repeat('a', 5001)], 'content'],
        ];
    }

    public function test_content_of_exactly_5000_characters_is_accepted(): void
    {
        // Boundary of the max:5000 rule — the longest message that must pass.
        $content = str_repeat('a', 5000);

        $this->postJson('/api/messages', $this->validPayload(['content' => $content]), $this->asUser($this->traveller))
            ->assertCreated();

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseHas('messages', ['content' => $content]);
    }

    public function test_validation_runs_before_actor_resolution(): void
    {
        // An invalid payload with no actor header must still produce 422,
        // not 401: the FormRequest validates before the controller runs.
        $this->postJson('/api/messages', $this->validPayload(['content' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }
}
