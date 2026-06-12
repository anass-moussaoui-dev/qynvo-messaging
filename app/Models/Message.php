<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{ 
    use HasFactory;

    protected $fillable = ['itinerary_id','sender_id','sender_type','content'];
    protected $casts = [
        'sender_type' => \App\Enums\UserType::class,
    ];


    public function itinerary()
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

}
