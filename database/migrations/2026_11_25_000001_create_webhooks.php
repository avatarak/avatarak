<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->text('secret');                       // encrypted; used to sign each delivery
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('failures')->default(0);   // consecutive failed deliveries
            $table->timestamp('last_delivery_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->timestamp('disabled_at')->nullable();           // set automatically after too many failures
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id');
            $table->string('event', 40);
            $table->json('payload');
            $table->unsignedSmallInteger('status')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 200)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['webhook_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
