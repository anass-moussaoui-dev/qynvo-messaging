<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Itinerary;
use App\Models\Message;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Policies\MessagePolicy;

class MessageController extends Controller
{

   /**
    * Retrieve all messages for a given itinerary, oldest first.
    */
   public function index(Request $request, Itinerary $itinerary)
    {
        $user = User::findOrFail($request->header('X-User-Id'));

        Gate::allowIf(app(MessagePolicy::class)->viewItineraryMessages($user, $itinerary));

        $messages = $itinerary->messages()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return MessageResource::collection($messages);
    }

    /**
     * Send a new message.
     */
    public function store(StoreMessageRequest $request)
    {
        $sender = User::findOrFail($request->validated('sender_id'));

        $message = Message::create([
            'itinerary_id' => $request->validated('itinerary_id'),
            'sender_id'    => $sender->id,
            'sender_type'  => $sender->type,   // derived — single source of truth
            'content'      => $request->validated('content'),
        ]);

        MessageSent::dispatch($message);

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}