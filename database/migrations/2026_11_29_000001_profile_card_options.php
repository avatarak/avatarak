<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('card_phone', 20)->nullable();            // optional, printed on the business card
            $table->boolean('card_phone_public')->default(false);    // also on the share image, the contact file and for visitors opening /print
            $table->string('card_style', 12)->default('classic');    // classic | band | bold
        });
    }

    public function down(): void
    {
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn(['card_phone', 'card_phone_public', 'card_style']));
    }
};
