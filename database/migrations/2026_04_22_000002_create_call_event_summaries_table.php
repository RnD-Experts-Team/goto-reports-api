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

            // Call data — use plain `timestamp` for cross-engine portability
            // (works on MySQL + older PostgreSQL; we always store UTC values).
            $table->timestamp('call_created')->nullable()->index();
            $table->timestamp('call_answered')->nullable();
            $table->timestamp('call_ended')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->string('direction', 16)->nullable();
            $table->string('caller_outcome', 32)->nullable();
            $table->string('call_initiator', 32)->nullable();
            $table->string('caller_number')->nullable();
            $table->string('caller_name')->nullable();
            $table->string('call_provider', 16)->nullable();
            $table->text('participants')->nullable();

            // Use TEXT for `raw` so the table works on PostgreSQL < 9.4
            // (no JSON/JSONB type) and on MySQL < 5.7 alike. We only ever
            // store/read it as a JSON-encoded string.
            $table->text('raw')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_event_summaries');
    }
};
