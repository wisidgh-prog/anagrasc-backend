<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //Modifier l'enum pour ajouter le statut "en_attente_validation"
    DB::statement("ALTER TABLE biens MODIFY COLUMN statut ENUM('en_attente_validation','publie','en_cours','vendu','annule') DEFAULT 'en_attente_validation'");
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE biens MODIFY COLUMN statut ENUM('publie','en_cours','vendu','annule') DEFAULT 'publie'");
    }
    
};
