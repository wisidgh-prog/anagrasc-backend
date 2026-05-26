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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Clé étrangère vers roles
            $table->foreignId('role_id')
                  ->constrained('roles')
                  ->restrictOnDelete();

            // Type de personne (null pour le personnel interne)
            $table->enum('type_personne', ['physique', 'morale'])->nullable();

            // Champs pour personne physique
            $table->string('nom')->nullable();
            $table->string('prenom')->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();

            // Champs pour personne morale
            $table->string('raison_sociale')->nullable();
            $table->string('num_rccm')->nullable();
            $table->string('num_ifu')->nullable();
            $table->string('representant_legal_nom')->nullable();
            $table->string('representant_legal_prenom')->nullable();
            $table->date('representant_legal_date_naissance')->nullable();
            $table->string('representant_legal_lieu_naissance')->nullable();
            $table->string('representant_legal_fonction')->nullable();

            // Coordonnées communes
            $table->string('telephone',191)->unique();
            $table->string('email',191)->nullable()->unique();
            $table->string('ville')->nullable();
            $table->string('password');

            // Pièce d'identité
            $table->enum('type_piece', [
                'cnib', 'passeport', 'carte_consulaire',
                'carte_aes', 'permis', 'rccm'
            ])->nullable();
            $table->string('num_piece')->nullable();
            $table->string('piece_recto_url')->nullable();
            $table->string('piece_verso_url')->nullable();

            // Statut du compte
            $table->boolean('est_valide')->default(false);
            $table->boolean('est_suspendu')->default(false);
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('valide_par')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->rememberToken();
            $table->timestamps();

            // Index pour les recherches fréquentes
            $table->index(['role_id', 'est_valide']);
            $table->index(['est_valide', 'est_suspendu']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
