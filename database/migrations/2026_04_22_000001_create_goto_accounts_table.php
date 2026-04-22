<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('goto_accounts', function (Blueprint $table) {
            $table->string('account_key')->primary();
            $table->string('organization_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('name_resolved_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goto_accounts');
    }
};
