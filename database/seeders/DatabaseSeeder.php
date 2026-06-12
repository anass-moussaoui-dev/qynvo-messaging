<?php

namespace Database\Seeders;

use App\Models\Itinerary;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Two participants — no authentication, just seeded actors.
        $traveller = User::factory()->traveller()->create(['name' => 'Ricardo Rios']);
        $agency = User::factory()->agency()->create(['name' => 'Anass Elmou']);

        // An itinerary linking this traveller and agency.
        $itinerary = Itinerary::factory()->create([
            'traveller_id' => $traveller->id,
            'agency_id' => $agency->id,
        ]);

        // A short, realistic traveller <-> agency conversation.
        $conversation = [
            [$traveller, 'Hi, what time is the airport pickup tomorrow?'],
            [$agency,    'Hello! Your driver will arrive at 9:00 AM at the hotel lobby.'],
            [$traveller, 'Perfect, thank you. Is the city tour still on for the afternoon?'],
            [$agency,    'Yes — the guide will meet you at 2:00 PM. Enjoy your trip!'],
        ];

        foreach ($conversation as [$sender, $content]) {
            Message::factory()
                ->for($itinerary)
                ->from($sender)
                ->create(['content' => $content]);
        }
    }
}
