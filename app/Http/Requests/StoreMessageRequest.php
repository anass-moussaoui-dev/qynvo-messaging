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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'itinerary_id' => ['required', 'integer', 'exists:itineraries,id'],
            'sender_id'    => ['required', 'integer', 'exists:users,id'],
            'content'      => ['required', 'string', 'max:5000'],
        ];
    }
}