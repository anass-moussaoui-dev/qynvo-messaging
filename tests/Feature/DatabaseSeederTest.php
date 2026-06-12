<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Itinerary;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the demo seed produces exactly the dataset the task describes:
 * one traveller and one agency, one itinerary linking them, and a four-message
 * conversation alternating between the two — with no stray rows.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_demo_conversation(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('itineraries', 1);
        $this->assertDatabaseCount('messages', 4);

        // sole() doubles as an assertion: it throws if there is not exactly
        // one matching row.
        $traveller = User::where('type', UserType::Traveller)->sole();
        $agency = User::where('type', UserType::Agency)->sole();
        $itinerary = Itinerary::query()->sole();

        $this->assertSame($traveller->id, $itinerary->traveller_id);
        $this->assertSame($agency->id, $itinerary->agency_id);

        // All four messages belong to the seeded itinerary and alternate
        // traveller → agency, like a real conversation.
        $messages = Message::orderBy('id')->get();

        $this->assertSame([$itinerary->id], $messages->pluck('itinerary_id')->unique()->all());
        $this->assertSame(
            ['traveller', 'agency', 'traveller', 'agency'],
            $messages->pluck('sender_type')->map(fn (UserType $type) => $type->value)->all()
        );
        $this->assertSame(
            [$traveller->id, $agency->id, $traveller->id, $agency->id],
            $messages->pluck('sender_id')->all()
        );
    }
}
