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
        Schema::create('biens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_id')
                  ->constrained('categories')
                  ->restrictOnDelete();
            $table->foreignId('cree_par')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->string('titre');
            $table->text('description');
            $table->decimal('mise_a_prix', 15, 2);
            $table->string('lieu_retrait');
            $table->string('horaires_visite')->nullable();
            $table->text('remarques')->nullable();

            
            $table->enum('statut', ['publie', 'en_cours', 'vendu', 'annule'])
                  ->default('publie');

            $table->timestamps();

            $table->index(['statut', 'categorie_id']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biens');
    }
};
