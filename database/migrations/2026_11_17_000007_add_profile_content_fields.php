<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('headline', 120)->nullable();
            $table->string('location', 100)->nullable();
            $table->json('links')->nullable();            // [{"label":"My blog","url":"https://..."}]
            $table->json('hidden_sections')->nullable();  // e.g. ["links"]
        });
    }

    public function down(): void
    {
        Schema::table('profiles', fn (Blueprint $t) => $t->dropColumn(['headline', 'location', 'links', 'hidden_sections']));
    }
};
