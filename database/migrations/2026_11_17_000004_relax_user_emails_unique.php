<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ownership of an address is decided by verification, not by who typed it first:
 * unverified claims may coexist; uniqueness among VERIFIED rows is enforced in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_emails', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropUnique(['email_hash']);
            $table->index('email_hash');
            $table->unique(['user_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('user_emails', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'email']);
            $table->dropIndex(['email_hash']);
            $table->unique('email');
            $table->unique('email_hash');
        });
    }
};
