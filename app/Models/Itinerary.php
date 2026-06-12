<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Itinerary extends Model
{
    use HasFactory;

    protected $fillable = ['traveller_id', 'agency_id'];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function traveller()
    {
        return $this->belongsTo(User::class, 'traveller_id');
    }

    public function agency()
    {
        return $this->belongsTo(User::class, 'agency_id');
    }
}
