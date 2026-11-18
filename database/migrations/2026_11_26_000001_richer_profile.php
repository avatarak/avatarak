<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('theme', 16)->default('gradient');
            $table->json('custom_sections')->nullable();
        });

        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');                 // full size (max 1600px); the thumbnail sits next to it as "<path>.thumb.jpg"
            $table->string('caption', 140)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->timestamps();
            $table->index(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_images');
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['theme', 'custom_sections']);
        });
    }
};
