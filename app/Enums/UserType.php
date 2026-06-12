<?php

namespace App\Enums;

/**
 * The two participant roles of an itinerary conversation.
 *
 * The backing values are part of the public contract: they are persisted in
 * users.type and messages.sender_type and serialized verbatim in API
 * responses, so renaming one is a breaking change.
 */
enum UserType: string
{
    case Traveller = 'traveller';
    case Agency = 'agency';
}
