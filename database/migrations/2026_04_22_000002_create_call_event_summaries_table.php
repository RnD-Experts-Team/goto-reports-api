<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('call_event_summaries', function (Blueprint $table) {
            $table->id();

            // Identity (used for upsert)
            $table->string('conversation_space_id')->unique();

            // Account linkage
            $table->string('account_key')->index();
            $table->string('organization_id')->nullable()->index();
            $table->string('account_name')->nullable()->index();

            // Call data
            $table->timestampTz('call_created')->nullable()->index();
            $table->timestampTz('call_answered')->nullable();
            $table->timestampTz('call_ended')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->string('direction', 16)->nullable();
            $table->string('caller_outcome', 32)->nullable();
            $table->string('call_initiator', 32)->nullable();
            $table->string('caller_number')->nullable();
            $table->string('caller_name')->nullable();
            $table->string('call_provider', 16)->nullable();
            $table->text('participants')->nullable();

            // Use portable JSON type (works on older PostgreSQL versions too)
            $table->json('raw')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_event_summaries');
    }
};
