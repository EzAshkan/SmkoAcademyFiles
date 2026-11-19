<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->index();
            $table->unsignedInteger('login_count')->default(0);
            $table->boolean('telegram_notifications')->default(true);
        });

        Schema::table('folder_user', function (Blueprint $table) {
            // Access ends at this moment (null = never).
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('granted_at')->nullable();
        });

        Schema::table('assets', function (Blueprint $table) {
            // When students were told about this file on Telegram (null = not yet).
            $table->timestamp('notified_at')->nullable()->index();
            $table->boolean('notify')->default(true);
            $table->index('created_at');
        });
        // Everything that already exists counts as announced.
        DB::table('assets')->update(['notified_at' => now()]);

        Schema::create('asset_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // view | download | telegram
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['asset_id', 'type']);
            $table->index(['user_id', 'asset_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('style', 16)->default('info'); // info | success | warning | danger
            $table->json('folder_ids')->nullable();          // null = everyone
            $table->boolean('show_banner')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('send_telegram')->default(false);
            $table->timestamp('telegram_sent_at')->nullable();
            $table->unsignedInteger('telegram_sent_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);        // created | updated | deleted | ...
            $table->string('subject_type', 32);  // folder | file | student | announcement | access
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary', 500);
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('health_checks', function (Blueprint $table) {
            $table->string('name', 32)->primary();
            $table->boolean('ok');
            $table->string('message', 500)->nullable();
            $table->timestamp('checked_at');
            $table->timestamp('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_checks');
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('asset_events');
        Schema::table('assets', fn (Blueprint $t) => $t->dropColumn(['notified_at', 'notify']));
        Schema::table('folder_user', fn (Blueprint $t) => $t->dropColumn(['expires_at', 'granted_at']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['last_login_at', 'login_count', 'telegram_notifications']));
    }
};
