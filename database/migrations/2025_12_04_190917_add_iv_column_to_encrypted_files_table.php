<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('encrypted_files', function (Blueprint $table) {
            $table->text('iv')->nullable()->after('encrypted_aes_key'); // IV pour AES-GCM
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('encrypted_files', function (Blueprint $table) {
            $table->dropColumn('iv');
        });
    }
};
