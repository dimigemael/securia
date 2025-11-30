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
        Schema::create('file_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('encrypted_files')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('encrypted_aes_key'); // Clé AES rechiffrée pour cet utilisateur
            $table->enum('permission_level', ['read', 'write', 'owner'])->default('read');
            $table->foreignId('granted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable(); // null = accès actif, non-null = révoqué
            $table->timestamps();

            // Index pour optimiser les recherches
            $table->index(['file_id', 'user_id']);
            $table->index('user_id');
            $table->index('revoked_at');

            // Une combinaison file_id + user_id doit être unique pour les accès actifs
            $table->unique(['file_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_accesses');
    }
};
