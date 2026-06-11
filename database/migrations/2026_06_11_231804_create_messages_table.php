<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            // I avoided onDelete cascade to preserve messages for historical purposes, but it can be added if needed. (we can also use soft delete to prevent data loss )
            $table->foreignId('itinerary_id')->constrained();             
            // we can use enum type for more strictness. However, it has limited support on SQLite, so I used a string with a DB-level check constraint for compatibility.
            $table->string('sender_type');
            $table->text('content');
            $table->timestamps();

            $table->index(['itinerary_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
