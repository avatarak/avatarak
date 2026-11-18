<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A human name for accounts whose identifier is an opaque id (LinkedIn): shown instead of a link.
        Schema::table('verified_accounts', fn (Blueprint $t) => $t->string('label', 120)->nullable()->after('identifier'));
    }

    public function down(): void
    {
        Schema::table('verified_accounts', fn (Blueprint $t) => $t->dropColumn('label'));
    }
};
