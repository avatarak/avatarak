<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 24);
            $table->string('period', 8);                       // month | year
            $table->unsignedBigInteger('amount');              // in config('plans.currency') units, fixed on OUR side when the payment is created
            $table->string('currency', 3)->default('IRT');
            $table->string('gateway', 16);
            $table->string('authority', 64)->nullable()->unique();   // the gateway's reference for this attempt
            $table->string('ref_id', 64)->nullable();                // the gateway's receipt number once paid
            $table->string('card_pan', 32)->nullable();
            $table->string('status', 10)->default('pending'); // pending | paid | failed
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // Time-boxed plan entitlements. A purchase and an administrator's grant both end up here.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 24);
            $table->string('source', 10);                      // payment | admin
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();   // one payment buys at most one period
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('payments');
    }
};
