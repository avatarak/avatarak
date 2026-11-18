<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('languages', 4)->default('fa');      // fa | en | both: which language(s) the public page speaks
            $table->string('primary_lang', 2)->default('fa');   // the language the existing text fields are written in
            $table->string('headline_alt', 120)->nullable();     // the same text in the other language
            $table->string('location_alt', 100)->nullable();
            $table->string('bio_alt', 500)->nullable();
        });
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->string('caption_alt', 140)->nullable();
        });

        // Nothing changes for anyone: a profile keeps speaking the language its text is already written in.
        DB::table('profiles')->orderBy('id')->each(function ($p) {
            $text = implode(' ', array_filter([$p->headline, $p->location, $p->bio]))
                .' '.collect(json_decode($p->links ?? '[]', true) ?: [])->map(fn ($l) => ($l['label'] ?? '').' '.($l['description'] ?? ''))->implode(' ');
            $lang = trim($text) === '' ? 'fa' : (preg_match('/[\x{0600}-\x{06FF}]/u', $text) ? 'fa' : 'en');
            DB::table('profiles')->where('id', $p->id)->update(['languages' => $lang, 'primary_lang' => $lang]);
        });
    }

    public function down(): void
    {
        Schema::table('gallery_images', fn (Blueprint $t) => $t->dropColumn('caption_alt'));
        Schema::table('profiles', fn (Blueprint $t) => $t->dropColumn(['languages', 'primary_lang', 'headline_alt', 'location_alt', 'bio_alt']));
    }
};
