<?php

namespace Tests\Feature\Messages;

use App\Models\Itinerary;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature tests for GET /api/messages/{itinerary}.
 *
 * Covers:
 *  - authorization through MessagePolicy (participants only, 403 otherwise)
 *  - actor identification via the X-User-Id header
 *  - route model binding (404 for unknown itineraries)
 *  - scoping (only the bound itinerary's messages are returned)
 *  - deterministic ordering: oldest first by created_at, ties broken by id —
 *    this mirrors the explicit orderBy('created_at')->orderBy('id') contract
 *    in the controller, it is not an artifact of insertion order
 *  - the exact MessageResource JSON shape
 */
class ListMessagesTest extends TestCase
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
        $this->agency    = User::factory()->agency()->create();
        $this->outsider  = User::factory()->create();

        $this->itinerary = Itinerary::factory()->create([
            'traveller_id' => $this->traveller->id,
            'agency_id'    => $this->agency->id,
        ]);
    }

    /**
     * Request headers identifying the acting user, mirroring the API contract.
     */
    private function asUser(User $user): array
    {
        return ['X-User-Id' => (string) $user->id];
    }

    private function listFor(Itinerary $itinerary, array $headers = [])
    {
        return $this->getJson("/api/messages/{$itinerary->id}", $headers);
    }

    // ──────────────────────────────────────────────────────── authorization

    public function test_traveller_can_list_messages_of_their_itinerary(): void
    {
        Message::factory()->count(3)->for($this->itinerary)->from($this->traveller)->create();

        $this->listFor($this->itinerary, $this->asUser($this->traveller))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_agency_can_list_messages_of_their_itinerary(): void
    {
        Message::factory()->count(2)->for($this->itinerary)->from($this->agency)->create();

        $this->listFor($this->itinerary, $this->asUser($this->agency))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_non_participant_is_forbidden(): void
    {
        Message::factory()->for($this->itinerary)->from($this->traveller)->create();

        $this->listFor($this->itinerary, $this->asUser($this->outsider))
            ->assertForbidden();
    }

    /**
     * Note: 401 Unauthorized would be the semantically correct status for a
     * request with no actor. 404 is the current behavior (User::findOrFail on
     * a null header) and this test pins it without endorsing it.
     */
    public function test_request_without_user_header_returns_404(): void
    {
        $this->listFor($this->itinerary)->assertNotFound();
    }

    public function test_request_with_unknown_user_header_returns_404(): void
    {
        $this->listFor($this->itinerary, ['X-User-Id' => '999999'])->assertNotFound();
    }

    public function test_unknown_itinerary_returns_404(): void
    {
        // Route model binding rejects the id before any policy check runs.
        $this->getJson('/api/messages/999999', $this->asUser($this->traveller))
            ->assertNotFound();
    }

    // ─────────────────────────────────────────────────── scoping & ordering

    public function test_only_messages_of_the_bound_itinerary_are_returned(): void
    {
        $mine  = Message::factory()->for($this->itinerary)->from($this->traveller)->create();
        $other = Message::factory()->create(); // belongs to a different itinerary

        $response = $this->listFor($this->itinerary, $this->asUser($this->traveller))->assertOk();

        $this->assertSame([$mine->id], $response->json('data.*.id'));
        $this->assertNotContains($other->id, $response->json('data.*.id'));
    }

    public function test_messages_are_ordered_oldest_first_with_id_tie_break(): void
    {
        // Created deliberately out of chronological order so insertion order
        // cannot mask a missing ORDER BY. The two 09:30 rows share a
        // timestamp; the lower id must win the tie.
        $late  = $this->messageAt('2026-06-12 10:00:00'); // newest
        $tieA  = $this->messageAt('2026-06-12 09:30:00'); // tie, lower id
        $tieB  = $this->messageAt('2026-06-12 09:30:00'); // tie, higher id
        $early = $this->messageAt('2026-06-12 09:00:00'); // oldest, created last

        $this->listFor($this->itinerary, $this->asUser($this->traveller))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$early->id, $tieA->id, $tieB->id, $late->id]);
    }

    public function test_itinerary_without_messages_returns_an_empty_collection(): void
    {
        $this->listFor($this->itinerary, $this->asUser($this->agency))
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    // ─────────────────────────────────────────────────────── response shape

    public function test_response_matches_the_message_resource_shape_exactly(): void
    {
        $this->travelTo(Carbon::create(2026, 6, 12, 10, 0, 0, 'UTC'));

        $message = Message::factory()
            ->for($this->itinerary)
            ->from($this->agency)
            ->create(['content' => 'Your driver will arrive at 9:00 AM.']);

        // assertExactJson: no stray fields (e.g. sender_id or updated_at) may
        // leak out of the resource.
        $this->listFor($this->itinerary, $this->asUser($this->traveller))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id'           => $message->id,
                        'itinerary_id' => $this->itinerary->id,
                        'sender_type'  => 'agency',
                        'content'      => 'Your driver will arrive at 9:00 AM.',
                        'created_at'   => '2026-06-12T10:00:00+00:00',
                    ],
                ],
            ]);
    }

    /**
     * Create a message on the shared itinerary with an explicit created_at.
     */
    private function messageAt(string $createdAt): Message
    {
        return Message::factory()
            ->for($this->itinerary)
            ->from($this->traveller)
            ->create(['created_at' => Carbon::parse($createdAt, 'UTC')]);
    }
}
