<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email')->unique();
            // sha256 of lowercased+trimmed email; used in avatar URLs
            $table->char('email_hash', 64)->unique();
            $table->string('verification_token', 64)->nullable()->unique();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_primary')->default(false);
            // per-email: may this address resolve to the avatar publicly?
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_emails');
    }
};
