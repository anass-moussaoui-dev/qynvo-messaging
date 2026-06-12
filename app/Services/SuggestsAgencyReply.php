<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates a short, context-aware reply suggestion for the agency in
 * response to a traveller message, using Anthropic's Claude Messages API.
 *
 * Runs synchronously inside the POST /api/messages request — the queued
 * MessageSent listener executes after the HTTP response has been sent, so
 * it could never contribute a field to that response (see README).
 *
 * Failure contract: this service NEVER throws. Missing API key, timeout,
 * HTTP error, or a malformed response all degrade to null so sending the
 * message itself is unaffected.
 */
class SuggestsAgencyReply
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * Fast, inexpensive replies: a small completion cap and a tight timeout.
     * Worst case the API holds the request 2 × 5s ≈ 10s before degrading.
     */
    private const MAX_TOKENS = 150;

    private const TIMEOUT_SECONDS = 5;

    /**
     * Rate limits and transient server errors are worth a single retry.
     * Client errors (400/401/422) are deterministic — retrying them only
     * adds latency to the traveller's request.
     */
    private const RETRYABLE_STATUSES = [429, 500, 529];

    private const CONTEXT_MESSAGES = 10;

    private const SYSTEM_PROMPT = 'You draft replies on behalf of a travel agency writing to its '
        .'traveller customer. Suggest a short, professional, friendly reply (one to three '
        .'sentences) to the traveller\'s latest message, using the conversation for context. '
        .'Respond with the reply text only - no preamble, no quotes, no signature.';

    /**
     * Suggest an agency reply to the given traveller message, or null when
     * no suggestion can be produced.
     */
    public function suggest(Message $message): ?string
    {
        $apiKey = config('services.anthropic.key');

        if (blank($apiKey)) {
            // Expected state (e.g. local dev, CI) — not an anomaly.
            Log::info('Suggested reply skipped: no Anthropic API key configured.');

            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->retry(2, 100, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && in_array($exception->response->status(), self::RETRYABLE_STATUSES, true));
                }, throw: false)
                ->post(self::ENDPOINT, [
                    'model' => config('services.anthropic.model'),
                    'max_tokens' => self::MAX_TOKENS,
                    'system' => self::SYSTEM_PROMPT,
                    'messages' => $this->conversation($message),
                ]);

            if ($response->failed()) {
                Log::warning('Suggested reply unavailable: Anthropic API error.', [
                    'status' => $response->status(),
                    'message_id' => $message->id,
                ]);

                return null;
            }

            return $this->extractText($response, $message);
        } catch (Throwable $exception) {
            // Load-bearing, not defensive: with throw=false a RequestException
            // is suppressed, but a ConnectionException (timeout, DNS) still
            // propagates once retries are exhausted.
            Log::warning('Suggested reply unavailable: '.$exception->getMessage(), [
                'message_id' => $message->id,
            ]);

            return null;
        }
    }

    /**
     * Build the Messages API conversation from recent itinerary history.
     *
     * Traveller messages map to the user role and agency messages to the
     * assistant role, so the model continues the thread as the agency. The
     * API requires the first entry to be a user turn, so leading agency
     * messages are dropped and consecutive same-role messages are coalesced
     * into a single turn.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function conversation(Message $message): array
    {
        $history = $message->itinerary->messages()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::CONTEXT_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        $turns = [];

        foreach ($history as $entry) {
            $role = $entry->sender_type === UserType::Traveller ? 'user' : 'assistant';

            if ($turns === [] && $role === 'assistant') {
                continue;
            }

            if ($turns !== [] && $turns[array_key_last($turns)]['role'] === $role) {
                $turns[array_key_last($turns)]['content'] .= "\n\n".$entry->content;

                continue;
            }

            $turns[] = ['role' => $role, 'content' => $entry->content];
        }

        return $turns;
    }

    /**
     * Pull the suggestion out of a successful Messages API response: the
     * first text block of `content` (not blindly content[0], which could be
     * a different block type).
     */
    private function extractText(Response $response, Message $message): ?string
    {
        $block = collect($response->json('content', []))->firstWhere('type', 'text');
        $text = is_array($block) ? trim((string) ($block['text'] ?? '')) : '';

        if ($text === '') {
            Log::warning('Suggested reply unavailable: Anthropic response had no text content.', [
                'message_id' => $message->id,
            ]);

            return null;
        }

        return $text;
    }
}
