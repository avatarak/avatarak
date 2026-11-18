<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // null = every scope (what all existing keys had).
        Schema::table('sites', fn (Blueprint $t) => $t->json('scopes')->nullable()->after('plan'));
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $t) => $t->dropColumn('scopes'));
    }
};
