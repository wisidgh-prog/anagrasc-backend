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
        Schema::table('sessions_enchere', function (Blueprint $table) {
          // Colonne offrant une référence circulaire vers la table offres
            // Nullable car remplie uniquement après clôture de l'enchère
            // nullOnDelete() évite de supprimer la session si l'offre est effacée
            $table->foreignId('offre_gagnante_id')
                  ->nullable()
                  ->constrained('offres')
                  ->nullOnDelete();

            // Index optionnel pour accélérer les jointures
            $table->index('offre_gagnante_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessions_enchere', function (Blueprint $table) {
            $table->dropForeign('sessions_enchere_offre_gagnante_id_foreign');
            $table->dropColumn('offre_gagnante_id');
        });
    }
};



