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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action'); // login, logout, encrypt, decrypt, share, revoke, etc.
            $table->string('entity_type')->nullable(); // File, User, etc.
            $table->unsignedBigInteger('entity_id')->nullable(); // ID de l'entité concernée
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable(); // Détails supplémentaires en JSON
            $table->enum('status', ['success', 'failure', 'warning'])->default('success');
            $table->text('error_message')->nullable(); // Message d'erreur si échec
            $table->timestamp('created_at'); // Horodatage de l'action

            // Index pour optimiser les recherches
            $table->index('user_id');
            $table->index('action');
            $table->index('entity_type');
            $table->index('created_at');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
