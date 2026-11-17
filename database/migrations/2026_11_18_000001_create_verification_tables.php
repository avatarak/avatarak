<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only PROVEN ownership lives here.
        Schema::create('verified_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('network', 20);                    // website | telegram
            $table->string('identifier');                     // domain, or telegram username at verification time
            $table->string('external_id', 64)->nullable();    // stable id (telegram numeric id); usernames can be re-assigned
            $table->string('method', 24);                     // meta | file | dns | telegram_bot
            $table->timestamp('verified_at');
            $table->timestamps();

            $table->unique(['network', 'identifier']);        // one owner per account
            $table->unique(['network', 'external_id']);
            $table->index('user_id');
        });

        // Pending proofs ("put this code on your site / send it to the bot").
        Schema::create('verification_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('network', 20);
            $table->string('identifier');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'network', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_challenges');
        Schema::dropIfExists('verified_accounts');
    }
};
