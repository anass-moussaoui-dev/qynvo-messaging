<?php

namespace Tests\Unit;

use App\Models\Itinerary;
use App\Models\User;
use App\Policies\MessagePolicy;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see MessagePolicy}.
 *
 * Both abilities (reading and sending) follow the same participant rule:
 * only the traveller or the agency assigned to an itinerary may act on it.
 *
 * The policy compares identifiers only, so these tests run against in-memory
 * models — no framework boot, no database. Ids are assigned via forceFill()
 * because `id` is intentionally not mass assignable.
 */
class MessagePolicyTest extends TestCase
{
    private const TRAVELLER_ID = 10;

    private const AGENCY_ID = 20;

    private const OUTSIDER_ID = 99;

    private MessagePolicy $policy;

    private Itinerary $itinerary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new MessagePolicy;

        $this->itinerary = (new Itinerary)->forceFill([
            'traveller_id' => self::TRAVELLER_ID,
            'agency_id' => self::AGENCY_ID,
        ]);
    }

    private function userWithId(int $id): User
    {
        return (new User)->forceFill(['id' => $id]);
    }

    public function test_traveller_of_the_itinerary_may_view_its_messages(): void
    {
        $this->assertTrue(
            $this->policy->viewItineraryMessages($this->userWithId(self::TRAVELLER_ID), $this->itinerary)
        );
    }

    public function test_agency_of_the_itinerary_may_view_its_messages(): void
    {
        $this->assertTrue(
            $this->policy->viewItineraryMessages($this->userWithId(self::AGENCY_ID), $this->itinerary)
        );
    }

    public function test_unrelated_user_may_not_view_itinerary_messages(): void
    {
        $this->assertFalse(
            $this->policy->viewItineraryMessages($this->userWithId(self::OUTSIDER_ID), $this->itinerary)
        );
    }

    public function test_traveller_of_the_itinerary_may_send_a_message(): void
    {
        $this->assertTrue(
            $this->policy->sendMessage($this->userWithId(self::TRAVELLER_ID), $this->itinerary)
        );
    }

    public function test_agency_of_the_itinerary_may_send_a_message(): void
    {
        $this->assertTrue(
            $this->policy->sendMessage($this->userWithId(self::AGENCY_ID), $this->itinerary)
        );
    }

    public function test_unrelated_user_may_not_send_a_message(): void
    {
        $this->assertFalse(
            $this->policy->sendMessage($this->userWithId(self::OUTSIDER_ID), $this->itinerary)
        );
    }
}
