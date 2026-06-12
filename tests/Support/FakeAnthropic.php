<?php

namespace Tests\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Reusable test double for the Anthropic Claude Messages API.
 *
 * Centralizes realistic request faking so no test hand-rolls API JSON.
 * The payload shapes mirror the real API — success envelope with content
 * blocks and an error envelope — so the service's extraction and failure
 * handling are exercised against reality, not a simplified stand-in.
 */
final class FakeAnthropic
{
    public const URL_PATTERN = 'api.anthropic.com/*';

    /**
     * Attempts made against a faked connection failure — Http::fake() does
     * not record requests whose stub throws, so timeout() counts them here.
     */
    public static int $attempts = 0;

    /**
     * Fake a successful Messages API response containing the given reply.
     */
    public static function reply(string $text): void
    {
        Http::fake([self::URL_PATTERN => Http::response(self::successBody($text))]);
    }

    /**
     * Fake an API error (realistic error envelope) on every attempt.
     */
    public static function error(int $status): void
    {
        Http::fake([self::URL_PATTERN => Http::response(self::errorBody($status), $status)]);
    }

    /**
     * Fake a connection failure (timeout, DNS) on every attempt. Inspect
     * self::$attempts afterwards to assert how many attempts were made.
     */
    public static function timeout(): void
    {
        self::$attempts = 0;

        Http::fake([self::URL_PATTERN => function (): void {
            self::$attempts++;

            throw new ConnectionException('Connection to api.anthropic.com timed out');
        }]);
    }

    /**
     * Fake a transient failure followed by a success — for retry tests.
     */
    public static function errorThenReply(int $status, string $text): void
    {
        Http::fake([
            self::URL_PATTERN => Http::sequence()
                ->push(self::errorBody($status), $status)
                ->push(self::successBody($text)),
        ]);
    }

    /**
     * A realistic Messages API success envelope.
     *
     * @return array<string, mixed>
     */
    public static function successBody(string $text): array
    {
        return [
            'id' => 'msg_01FakeAnthropicTestResponse',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-haiku-4-5',
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 120, 'output_tokens' => 35],
        ];
    }

    /**
     * A realistic Messages API error envelope.
     *
     * @return array<string, mixed>
     */
    public static function errorBody(int $status): array
    {
        $type = match ($status) {
            400 => 'invalid_request_error',
            401 => 'authentication_error',
            429 => 'rate_limit_error',
            529 => 'overloaded_error',
            default => 'api_error',
        };

        return [
            'type' => 'error',
            'error' => ['type' => $type, 'message' => 'Fake '.$type.' from FakeAnthropic'],
            'request_id' => 'req_test_fake',
        ];
    }
}
