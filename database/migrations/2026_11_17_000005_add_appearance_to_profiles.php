<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('accent_color', 7)->nullable();   // "#RRGGBB"; null = brand default
            $table->json('section_order')->nullable();        // e.g. ["bio","links","website"]
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['accent_color', 'section_order']);
        });
    }
};
