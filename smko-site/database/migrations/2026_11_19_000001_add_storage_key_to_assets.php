<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Exact object key in the bucket when it isn't {prefix}/{id}.{ext}
            // (the old site stored newer uploads as smko-files/assets/{folder}/{random}.{ext}).
            $table->string('storage_key', 512)->nullable()->after('file_ext');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('storage_key');
        });
    }
};
