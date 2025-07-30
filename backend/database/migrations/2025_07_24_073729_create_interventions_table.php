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
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();

            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();

            // Relations (assumant qu'il y a des tables `users` et `printers`)
            $table->foreignId('client_id')->constrained('users')->onDelete('set null')->nullable();
            $table->foreignId('technician_id')->constrained('users')->onDelete('set null')->nullable();
            $table->foreignId('printer_id')->constrained('printers')->onDelete('cascade');

            $table->string('status')->default('En Attente');
            $table->text('description')->nullable();
            $table->string('intervention_type')->nullable(); // Type d'intervention, par exemple 'Maintenance', 'Réparation', etc.
            $table->string('priority')->default('Moyenne');
            $table->string('numero_demande')->unique(); // Code unique pour l'intervention
            $table->text('solution')->nullable(); // Solution apportée, si applicable
            $table->dateTime('date_previsionnelle')->nullable(); // Date prévisionnelle de l'intervention
            $table->string('image_path')->nullable(); // Chemin de l'image associée à l'intervention
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
