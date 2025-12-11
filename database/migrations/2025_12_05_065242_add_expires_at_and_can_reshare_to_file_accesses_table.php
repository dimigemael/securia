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
        Schema::table('file_accesses', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('granted_at');
            $table->boolean('can_reshare')->default(false)->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file_accesses', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'can_reshare']);
        });
    }
};
