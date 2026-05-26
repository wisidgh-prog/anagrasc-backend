<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions_enchere', function (Blueprint $table) {
            $table->id();

            // Bien mis en vente
            $table->foreignId('bien_id')
                  ->constrained('biens')
                  ->restrictOnDelete();

            // Commissaire‑priseur responsable
            $table->foreignId('commissaire_id')
                  ->constrained('users')
                  ->restrictOnDelete();

            // Superviseur affecté (optionnel)
            $table->foreignId('superviseur_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->dateTime('date_debut');                // début de l'enchère
            $table->dateTime('date_fin');                  // clôture stricte
            $table->decimal('mise_a_prix_effective', 15, 2);
            $table->decimal('montant_min_surenchere', 15, 2)->default(1000);
            $table->decimal('prix_reserve', 15, 2)->nullable(); // confidentiel

            $table->enum('statut', ['a_venir', 'en_cours', 'terminee', 'interrompue'])
                  ->default('a_venir');

            $table->timestamps();

            $table->index(['statut', 'date_fin']);    // pour le cron de clôture
            $table->index(['statut', 'date_debut']);  // pour le cron de démarrage
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions_enchere');
    }
};
