<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of a message.
 *
 * Deliberately omits sender_id and updated_at: clients only need who spoke
 * (by role) and when — the contract is pinned by the API tests.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'itinerary_id' => $this->itinerary_id,
            'sender_type' => $this->sender_type->value,
            'content' => $this->content,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
