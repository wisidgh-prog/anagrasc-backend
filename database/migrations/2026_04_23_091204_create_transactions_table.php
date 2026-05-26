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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // Enchérisseur concerné
            $table->foreignId('encherisseur_id')
                  ->constrained('encherisseurs')
                  ->restrictOnDelete();

            // Session d'enchère associée (peut être nulle pour un simple dépôt)
            $table->foreignId('session_id')
                  ->nullable()
                  ->constrained('sessions_enchere')
                  ->nullOnDelete();

            // Types de transactions (cf. CDC)
            $table->enum('type', [
                'depot',                 // recharge du wallet
                'prelevement_caution',   // caution débitée à la première offre
                'remboursement_caution', // caution rendue aux perdants
                'paiement_final',        // prix adjugé + frais
                'penalite'               // caution perdue (défaillance)
            ]);

            // Montant en FCFA
            $table->decimal('montant', 15, 2);

            // Statut de la transaction
            $table->enum('statut', ['ok', 'echec', 'en_attente'])
                  ->default('ok');

            // Référence externe (opérateur mobile)
            $table->string('reference_externe')->nullable();

            // Description / motif
            $table->text('description')->nullable();

            $table->timestamps();

            // Index pour accélérer les recherches par enchérisseur et type
            $table->index(['encherisseur_id', 'type']);

            // Index pour filtrer les transactions d'une session
            $table->index(['session_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
