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
     *
     * The sender is the acting user (X-User-Id header), never a payload value,
     * so identity cannot be spoofed through the request body.
     */
    public function store(StoreMessageRequest $request)
    {
        $sender    = User::findOrFail($request->header('X-User-Id'));
        $itinerary = Itinerary::findOrFail($request->validated('itinerary_id'));

        // Gate::allowIf instead of $this->authorize(): policy discovery maps
        // MessagePolicy to the Message model, not to the Itinerary passed here.
        Gate::allowIf(app(MessagePolicy::class)->sendMessage($sender, $itinerary));

        $message = Message::create([
            'itinerary_id' => $itinerary->id,
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