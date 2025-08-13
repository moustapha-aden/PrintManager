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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->string('roleDisplay'); // Admin, Client, Technicien
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('set null'); // La société n'est obligatoire que pour les clients
            $table->string('status'); // active, inactive
            $table->string('statusDisplay')->default('active'); // Actif, Inactif
            $table->string('phone')->nullable(); // Ajouté pour le téléphone
            $table->timestamp('lastLogin')->nullable(); // Date de dernière connexion
            $table->string('requestsHandled')->default('N/A'); // Pour techniciens/clients, peut être un int
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
