<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('last_used_at');
        });

        // Abuse reports filed by visitors against a public profile.
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 24);                               // spam | impersonation | inappropriate | illegal | other
            $table->string('details', 500)->nullable();
            $table->string('reporter_hash', 64)->nullable();            // keyed hash of the reporter's IP, only to stop duplicates
            $table->string('status', 12)->default('open');              // open | actioned | dismissed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['profile_id', 'reporter_hash']);
        });

        // Every administrator action, newest first. Never edited, never deleted by the app.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_label', 190);                         // survives the admin being deleted
            $table->string('action', 40);
            $table->string('target_type', 20)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_label', 190)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reports');
        Schema::table('sites', fn (Blueprint $t) => $t->dropColumn('disabled_at'));
    }
};
