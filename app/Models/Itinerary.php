<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Itinerary extends Model
{
    use HasFactory;
    protected $fillable = ['traveller_name', 'agency_name'];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
