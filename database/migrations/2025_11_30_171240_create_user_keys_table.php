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
        Schema::create('user_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('public_key'); // Clé publique RSA (stockée en clair)
            $table->text('encrypted_private_key'); // Clé privée RSA chiffrée avec le mot de passe de l'utilisateur
            $table->string('key_algorithm')->default('RSA'); // RSA ou ECC
            $table->integer('key_size')->default(2048); // Taille de la clé (2048, 4096, etc.)
            $table->timestamps();

            // Index pour optimiser les recherches
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_keys');
    }
};
