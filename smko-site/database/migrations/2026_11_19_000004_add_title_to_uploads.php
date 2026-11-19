<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Title and description typed in the upload panel before the upload starts.
        Schema::table('uploads', function (Blueprint $table) {
            $table->string('title')->nullable()->after('filename');
            $table->text('description')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', fn (Blueprint $t) => $t->dropColumn(['title', 'description']));
    }
};
