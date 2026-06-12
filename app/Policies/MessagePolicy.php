<?php

namespace App\Policies;

use App\Models\Itinerary;
use App\Models\User;

class MessagePolicy
{
    /**
     * Determine whether the user may read the messages of an itinerary.
     *
     * Only the traveller or the agency assigned to the itinerary may read it.
     */
    public function viewItineraryMessages(User $user, Itinerary $itinerary): bool
    {
        return $this->isParticipant($user, $itinerary);
    }

    /**
     * Determine whether the user may send a message on an itinerary.
     *
     * Only the traveller or the agency assigned to the itinerary may write to it.
     */
    public function sendMessage(User $user, Itinerary $itinerary): bool
    {
        return $this->isParticipant($user, $itinerary);
    }

    /**
     * A user participates in an itinerary as either its traveller or its agency.
     */
    private function isParticipant(User $user, Itinerary $itinerary): bool
    {
        return $user->id === $itinerary->traveller_id
            || $user->id === $itinerary->agency_id;
    }
}
