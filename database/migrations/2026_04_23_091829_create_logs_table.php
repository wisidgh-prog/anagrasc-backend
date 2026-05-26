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
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            
          // Utilisateur à l'origine de l'action (peut être null si action système)
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Type d'action (ex: 'validation_compte', 'adjudication', 'interruption_enchere')
            $table->string('action');

            // Description détaillée de l'action
            $table->text('description');

            // Métadonnées supplémentaires (au format JSON)
            $table->json('meta')->nullable();

            // Horodatage précis à la seconde (exigence CDC Module 8)
            $table->timestamp('horodatage')->useCurrent();

            // Index pour accélérer les recherches par utilisateur, action et date
            $table->index(['user_id', 'action']);
            $table->index('horodatage');
            $table->index('action');

            // Pas de timestamps() car les logs sont immuables (pas de updated_at)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
