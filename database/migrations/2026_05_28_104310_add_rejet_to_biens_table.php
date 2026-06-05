<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->text('motif_rejet')->nullable()->after('remarques');
            $table->timestamp('date_rejet')->nullable()->after('motif_rejet');
        });
        // Ajouter le statut 'rejete'
        DB::statement("ALTER TABLE biens MODIFY COLUMN statut ENUM('en_attente_validation','publie','en_cours','vendu','annule','rejete') DEFAULT 'en_attente_validation'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE biens MODIFY COLUMN statut ENUM('en_attente_validation','publie','en_cours','vendu','annule') DEFAULT 'en_attente_validation'");
        Schema::table('biens', function (Blueprint $table) {
            $table->dropColumn('motif_rejet','date_rejet');

        });
    }
};
