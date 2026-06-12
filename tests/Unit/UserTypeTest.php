<?php

namespace Tests\Unit;

use App\Enums\UserType;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the {@see UserType} enum.
 *
 * The backing values are part of the public API contract: they are stored in
 * the users.type and messages.sender_type columns and serialized verbatim in
 * MessageResource responses. Renaming a value is a breaking change, which is
 * why these tests pin the exact strings.
 */
class UserTypeTest extends TestCase
{
    public function test_it_has_exactly_the_traveller_and_agency_cases(): void
    {
        $this->assertSame(
            [UserType::Traveller, UserType::Agency],
            UserType::cases()
        );
    }

    public function test_backing_values_match_the_api_contract_strings(): void
    {
        $this->assertSame('traveller', UserType::Traveller->value);
        $this->assertSame('agency', UserType::Agency->value);
    }

    public function test_contract_strings_resolve_back_to_their_cases(): void
    {
        $this->assertSame(UserType::Traveller, UserType::from('traveller'));
        $this->assertSame(UserType::Agency, UserType::from('agency'));
    }
}
