<?php

namespace Database\Seeders;

use App\Enums\SenderType;
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
        $itinerary = Itinerary::factory()->create([
            'traveller_id' => 1,
            'agency_id'    => 1,
        ]);

        // conversations that I generated with Ai.
        $conversation = [
            [SenderType::Traveller, 'Hi, what time is the airport pickup tomorrow?'],
            [SenderType::Agency,    'Hello! Your driver will arrive at 9:00 AM at the hotel lobby.'],
            [SenderType::Traveller, 'Perfect, thank you. Is the city tour still on for the afternoon?'],
            [SenderType::Agency,    'Yes — the guide will meet you at 2:00 PM. Enjoy your trip!'],
        ];

        foreach ($conversation as [$sender, $content]) {
            Message::factory()->for($itinerary)->create([
                'sender_type' => $sender,
                'content'     => $content,
            ]);
        }
    }
}
