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
        Schema::create('offres', function (Blueprint $table) {
            $table->id();

            // Clé étrangère vers la session d'enchère
            $table->foreignId('session_id')
                  ->constrained('sessions_enchere')
                  ->cascadeOnDelete();

            // Clé étrangère vers l'enchérisseur (personne réelle)
            $table->foreignId('encherisseur_id')
                  ->constrained('encherisseurs')
                  ->restrictOnDelete();

            // Montant de l'offre en FCFA (15 chiffres dont 2 décimales)
            $table->decimal('montant', 15, 2);

            // ID anonyme format ENCH-001 (séquentiel par session)
            $table->string('id_anonyme');

            // Indique si cette offre est la gagnante finale
            $table->boolean('est_gagnante')->default(false);

            // Rang de l'offre (1 = gagnant, 2 = second, etc.) pour le report en cas de défaillance
            $table->integer('rang')->nullable();

            $table->timestamps();

            // Index pour accélérer la recherche de la meilleure offre
            $table->index(['session_id', 'montant']);

            // Index pour vérifier la participation d'un enchérisseur à une session
            $table->index(['session_id', 'encherisseur_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offres');
    }
};
