<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                  ->constrained('sessions_enchere')
                  ->restrictOnDelete();
            $table->foreignId('superviseur_id')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->foreignId('traite_par')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->string('type_incident');
            $table->text('description');
            $table->string('id_concerne')->nullable();
            $table->enum('statut', ['ouvert', 'traite', 'clos'])
                  ->default('ouvert');
            $table->enum('decision', ['maintien', 'annulation', 'reprogrammation'])
                  ->nullable();
            $table->text('note_decision')->nullable();
            $table->timestamp('date_signalement')->useCurrent();
            $table->timestamp('date_traitement')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
