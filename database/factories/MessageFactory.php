<?php

namespace Database\Factories;

use App\Enums\UserType;
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
     * The sender is a lazy nested factory: it is only resolved when a test
     * does not override the sender (e.g. via from()), so no stray users are
     * created as a side effect.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'itinerary_id' => Itinerary::factory(),
            'sender_id'    => User::factory()->traveller(),
            'sender_type'  => UserType::Traveller,
            'content'      => fake()->sentence(),
        ];
    }

    /**
     * Set the sender, deriving sender_type from the user — mirrors how the
     * controller persists messages.
     */
    public function from(User $sender): static
    {
        return $this->state(fn () => [
            'sender_id'   => $sender->id,
            'sender_type' => $sender->type,
        ]);
    }
}
