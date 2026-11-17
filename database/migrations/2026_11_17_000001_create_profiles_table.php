<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username', 32)->unique();
            $table->string('name_fa')->nullable();
            $table->string('name_en')->nullable();
            $table->text('bio')->nullable();
            $table->string('website')->nullable();
            // {"telegram": "...", "instagram": "...", "x": "...", "github": "...", "linkedin": "..."}
            $table->json('socials')->nullable();
            $table->string('avatar_path')->nullable();
            // used as ETag / cache-busting key for CDN and Redis
            $table->string('avatar_version', 16)->nullable();

            // Privacy controls
            $table->boolean('show_avatar')->default(true);
            $table->boolean('show_profile')->default(true);
            $table->boolean('allow_indexing')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
