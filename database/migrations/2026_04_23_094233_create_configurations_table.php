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
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();

            // Clé unique identifiant le paramètre (ex: 'taux_caution')
            $table->string('cle')->unique();

            // Valeur stockée sous forme de chaîne (à convertir selon le besoin)
            $table->string('valeur');

            // Description lisible du paramètre pour l'interface admin
            $table->string('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configurations');
    }
};
