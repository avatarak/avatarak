<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // null = "use what the owner's plan allows"; a number is an administrator's explicit override (up or down).
        Schema::table('sites', fn (Blueprint $t) => $t->unsignedInteger('rate_limit_per_minute')->nullable()->default(null)->change());
        DB::table('sites')->where('rate_limit_per_minute', 600)->update(['rate_limit_per_minute' => null]);   // the old default
    }

    public function down(): void
    {
        DB::table('sites')->whereNull('rate_limit_per_minute')->update(['rate_limit_per_minute' => 600]);
        Schema::table('sites', fn (Blueprint $t) => $t->unsignedInteger('rate_limit_per_minute')->default(600)->nullable(false)->change());
    }
};
