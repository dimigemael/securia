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
        Schema::create('encrypted_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('filename'); // Nom du fichier chiffré sur le serveur
            $table->string('original_name'); // Nom original du fichier
            $table->string('file_path'); // Chemin du fichier chiffré
            $table->bigInteger('file_size')->unsigned(); // Taille du fichier en octets
            $table->text('encrypted_aes_key'); // Clé AES chiffrée pour le propriétaire
            $table->text('signature'); // Signature numérique du fichier
            $table->string('hash'); // Hash SHA-256 du fichier original
            $table->string('mime_type')->nullable(); // Type MIME du fichier
            $table->string('encryption_algorithm')->default('AES-256-GCM'); // Algorithme de chiffrement
            $table->timestamps();
            $table->softDeletes(); // Pour permettre la récupération des fichiers supprimés

            // Index pour optimiser les recherches
            $table->index('owner_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encrypted_files');
    }
};
