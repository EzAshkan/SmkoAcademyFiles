<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('folders')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // AUDIO | DOCUMENT | PHOTO | TEXT | VIDEO
            $table->string('type', 16);
            // Bucket object is downloads/{id}.{file_ext}; null for TEXT items.
            $table->string('file_ext', 16)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            // Size as shown by the old site ("2.43MB"), kept until the bucket sync fills size_bytes.
            $table->string('size_label', 32)->nullable();
            $table->text('description')->nullable();
            $table->longText('text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['folder_id', 'sort_order']);
        });

        Schema::create('folder_user', function (Blueprint $table) {
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['folder_id', 'user_id']);
        });

        Schema::create('telegram_claims', function (Blueprint $table) {
            $table->string('token', 32)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_claims');
        Schema::dropIfExists('folder_user');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('folders');
    }
};
