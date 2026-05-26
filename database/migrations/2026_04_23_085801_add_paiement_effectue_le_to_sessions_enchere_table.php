<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions_enchere', function (Blueprint $table) {
            // Champ pour tracer la date/heure du paiement du gagnant
            // NULL = non payé, NOT NULL = paiement confirmé
            // Utilisé par RelancePaiementsCommand pour détecter les défaillances après 48h
            $table->timestamp('paiement_effectue_le')
                  ->nullable()
                  ->after('offre_gagnante_id');
        });
    }

    public function down(): void
    {
        Schema::table('sessions_enchere', function (Blueprint $table) {
            $table->dropColumn('paiement_effectue_le');
        });
    }
};
