<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avatars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::table('profiles', function (Blueprint $table) {
            // which library avatar is active (profiles.avatar_path is a denormalised copy used by the public endpoint)
            $table->unsignedBigInteger('avatar_id')->nullable();
        });

        // Backfill: existing single avatars become library entries.
        foreach (DB::table('profiles')->whereNotNull('avatar_path')->get() as $p) {
            $id = DB::table('avatars')->insertGetId([
                'user_id' => $p->user_id, 'path' => $p->avatar_path, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('profiles')->where('id', $p->id)->update(['avatar_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('profiles', fn (Blueprint $t) => $t->dropColumn('avatar_id'));
        Schema::dropIfExists('avatars');
    }
};
