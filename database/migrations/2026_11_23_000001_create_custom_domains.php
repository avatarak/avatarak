<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('host', 190)->unique();            // lower-case, no www
            $table->string('token', 48);                      // proves ownership through a DNS TXT record
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedSmallInteger('checks')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });

        // How many domains a plan allows.
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domains');
    }
};
