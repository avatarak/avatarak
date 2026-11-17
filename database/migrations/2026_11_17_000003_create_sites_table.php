<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('domain');
            // only the hash of the API key is stored; plain key is shown once
            $table->char('api_key_hash', 64)->unique();
            $table->string('api_key_prefix', 12);
            $table->string('plan', 16)->default('free'); // free | white_label
            $table->boolean('white_label')->default(false);
            $table->unsignedInteger('rate_limit_per_minute')->default(600);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
