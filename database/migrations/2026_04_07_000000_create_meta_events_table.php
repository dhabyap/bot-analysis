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
        Schema::create('meta_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name')->index();
            $table->string('source')->nullable();
            $table->decimal('value', 15, 2)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            
            // Index to speed up daily aggregations
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_events');
    }
};
