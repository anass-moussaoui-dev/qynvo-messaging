<?php

namespace Database\Factories;

use App\Models\Itinerary;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sender = User::factory()->create();

        return [
            'itinerary_id' => Itinerary::factory(),
            'sender_id'    => $sender->id,
            'sender_type'  => $sender->type, 
            'content'      => fake()->sentence(),
        ];
    }

    public function from(User $sender): static
    {
        return $this->state(fn () => [
            'sender_id'   => $sender->id,
            'sender_type' => $sender->type,
        ]);
    }
}