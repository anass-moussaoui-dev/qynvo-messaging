<?php

namespace App\Http\Controllers;

use App\Enums\UserType;
use App\Events\MessageSent;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Itinerary;
use App\Models\Message;
use App\Models\User;
use App\Policies\MessagePolicy;
use App\Services\SuggestsAgencyReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Messaging endpoints for an itinerary's traveller ↔ agency conversation.
 *
 * Authorization uses Gate::allowIf with an injected MessagePolicy rather than
 * $this->authorize(): policy auto-discovery maps MessagePolicy to the Message
 * model, so ability checks against an Itinerary would not resolve to it.
 */
class MessageController extends Controller
{
    public function __construct(
        private readonly MessagePolicy $policy,
        private readonly SuggestsAgencyReply $suggestsAgencyReply,
    ) {}

    /**
     * Retrieve all messages for a given itinerary, oldest first.
     */
    public function index(Request $request, Itinerary $itinerary): AnonymousResourceCollection
    {
        Gate::allowIf($this->policy->viewItineraryMessages($this->actingUser($request), $itinerary));

        $messages = $itinerary->messages()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return MessageResource::collection($messages);
    }

    /**
     * Send a new message.
     *
     * The sender is the acting user, never a payload value, so identity
     * cannot be spoofed through the request body.
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        $sender = $this->actingUser($request);
        $itinerary = Itinerary::findOrFail($request->validated('itinerary_id'));

        Gate::allowIf($this->policy->sendMessage($sender, $itinerary));

        $message = Message::create([
            'itinerary_id' => $itinerary->id,
            'sender_id' => $sender->id,
            'sender_type' => $sender->type, // derived — single source of truth
            'content' => $request->validated('content'),
        ]);

        MessageSent::dispatch($message);

        // Synchronous by design: the queued listener runs after this response
        // is sent, so it could never populate suggested_reply (see README).
        // Agency replies are only suggested for traveller messages.
        $suggestedReply = $sender->type === UserType::Traveller
            ? $this->suggestsAgencyReply->suggest($message)
            : null;

        return (new MessageResource($message))
            ->additional(['suggested_reply' => $suggestedReply])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Resolve the acting user from the X-User-Id header — this task's
     * stand-in for real authentication.
     *
     * A missing or unknown actor is an authentication failure (401), kept
     * distinct from 403 (valid actor, not a participant) and 404 (the
     * itinerary does not exist).
     */
    private function actingUser(Request $request): User
    {
        $user = User::find($request->header('X-User-Id'));

        abort_if($user === null, Response::HTTP_UNAUTHORIZED, 'A valid X-User-Id header is required.');

        return $user;
    }
}
