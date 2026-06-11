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
        Schema::create('itineraries', function (Blueprint $table) {
            $table->id();
            // we use plain unsignedBigInteger and not foreignId()->constrained()? Because there are no travellers or agencies tables to reference at the moment. the task explicitly scopes out the user system. 
            $table->unsignedBigInteger('traveller_id');
            $table->unsignedBigInteger('agency_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itineraries');
    }
};
