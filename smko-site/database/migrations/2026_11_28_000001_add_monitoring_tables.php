<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every batch of Telegram messages the site hands to the bot.
        Schema::create('telegram_sends', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 24);          // new_files | announcement | monitoring | error
            $table->string('label', 255)->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('queued')->default(0);
            $table->boolean('failed')->default(false);
            $table->string('error', 300)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Bytes streamed through this server, per day and file.
        Schema::create('traffic_daily', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('kind', 12);          // download | preview
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('hits')->default(0);
            $table->unique(['day', 'asset_id', 'kind']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_daily');
        Schema::dropIfExists('telegram_sends');
    }
};
