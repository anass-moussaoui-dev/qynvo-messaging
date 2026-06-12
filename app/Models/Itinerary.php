<?php

namespace App\Models;

use Database\Factories\ItineraryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Itinerary extends Model
{
    /** @use HasFactory<ItineraryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['traveller_id', 'agency_id'];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function traveller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traveller_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agency_id');
    }
}
