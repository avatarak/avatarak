<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aggregated counters only: no IP addresses, no user agents, nothing per visitor.
        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('metric', 16);               // profile | avatar | export | api
            $table->unsignedInteger('count')->default(0);

            $table->unique(['user_id', 'date', 'metric']);
            $table->index(['date', 'metric']);
        });

        // Which sites showed the user's avatar / profile (host of the Referer, or the API key's site).
        Schema::create('stat_referrers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('metric', 16);
            $table->string('host', 100);
            $table->unsignedInteger('count')->default(0);

            $table->unique(['user_id', 'date', 'metric', 'host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stat_referrers');
        Schema::dropIfExists('daily_stats');
    }
};
