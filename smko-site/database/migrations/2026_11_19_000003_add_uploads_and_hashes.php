<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // MD5 of the file's content — used to spot an upload that's already in the bucket.
            $table->string('content_md5', 32)->nullable()->index();
        });

        // Background uploads from the admin panel (sent in chunks).
        Schema::create('uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->unsignedBigInteger('size');
            $table->string('md5', 32)->nullable();
            $table->unsignedBigInteger('received')->default(0);
            $table->boolean('notify')->default(true);
            $table->string('status', 16)->default('uploading'); // uploading | done | failed
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
        Schema::table('assets', fn (Blueprint $t) => $t->dropColumn('content_md5'));
    }
};
