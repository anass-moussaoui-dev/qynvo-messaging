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
        return $user->id === $itinerary->traveller_id
            || $user->id === $itinerary->agency_id;
    }
}