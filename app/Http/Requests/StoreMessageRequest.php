<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The sender is not part of the payload: the acting user is identified by
     * the X-User-Id header (a stand-in for real authentication in this task)
     * and resolved server-side in the controller.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'itinerary_id' => ['required', 'integer', 'exists:itineraries,id'],
            'content'      => ['required', 'string', 'max:5000'],
        ];
    }
}