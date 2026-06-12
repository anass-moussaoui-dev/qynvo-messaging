<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Itinerary;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for the Eloquent layer: relationships round-tripping
 * through the database, enum casting on hydration, and the factory states the
 * rest of the suite (and the seeder) rely on.
 */
class ModelsTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────── relationships

    public function test_itinerary_has_many_messages(): void
    {
        $itinerary = Itinerary::factory()->create();
        $messages = Message::factory()->count(2)->for($itinerary)->create();
        Message::factory()->create(); // different itinerary — must not appear

        $this->assertEqualsCanonicalizing(
            $messages->pluck('id')->all(),
            $itinerary->messages()->pluck('id')->all()
        );
    }

    public function test_message_belongs_to_its_itinerary_and_sender(): void
    {
        $sender = User::factory()->traveller()->create();
        $itinerary = Itinerary::factory()->create(['traveller_id' => $sender->id]);
        $message = Message::factory()->for($itinerary)->from($sender)->create();

        $this->assertTrue($message->itinerary->is($itinerary));
        $this->assertTrue($message->sender->is($sender));
    }

    public function test_itinerary_belongs_to_its_traveller_and_agency(): void
    {
        $traveller = User::factory()->traveller()->create();
        $agency = User::factory()->agency()->create();

        $itinerary = Itinerary::factory()->create([
            'traveller_id' => $traveller->id,
            'agency_id' => $agency->id,
        ]);

        $this->assertTrue($itinerary->traveller->is($traveller));
        $this->assertTrue($itinerary->agency->is($agency));
    }

    // ──────────────────────────────────────────────────────────── enum casts

    public function test_user_type_is_hydrated_as_a_user_type_enum(): void
    {
        $user = User::factory()->agency()->create();

        $this->assertSame(UserType::Agency, $user->fresh()->type);
    }

    public function test_message_sender_type_is_hydrated_as_a_user_type_enum(): void
    {
        $sender = User::factory()->agency()->create();
        $message = Message::factory()->from($sender)->create();

        $this->assertSame(UserType::Agency, $message->fresh()->sender_type);
    }

    // ─────────────────────────────────────────────────────── factory states

    public function test_user_factory_states_set_the_type(): void
    {
        $this->assertSame(UserType::Traveller, User::factory()->traveller()->create()->type);
        $this->assertSame(UserType::Agency, User::factory()->agency()->create()->type);
    }

    public function test_message_factory_from_state_copies_the_sender_identity(): void
    {
        $sender = User::factory()->agency()->create();
        $message = Message::factory()->from($sender)->create();

        $this->assertSame($sender->id, $message->sender_id);
        // sender_type must always mirror the sender's type — the same
        // derivation rule the controller applies when persisting.
        $this->assertSame($sender->type, $message->sender_type);
    }

    public function test_itinerary_factory_creates_a_valid_participant_pair(): void
    {
        $itinerary = Itinerary::factory()->create();

        $this->assertSame(UserType::Traveller, $itinerary->traveller->type);
        $this->assertSame(UserType::Agency, $itinerary->agency->type);
    }
}
