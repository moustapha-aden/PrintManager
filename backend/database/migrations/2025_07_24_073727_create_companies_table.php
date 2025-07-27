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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Nom de la société, doit être unique
            $table->string('address')->nullable(); // Adresse de la société
            $table->string('country')->nullable(); // Pays
            $table->string('phone')->nullable(); // Numéro de téléphone
            $table->string('email')->nullable(); // E-mail de contact général
            $table->string('contact_person')->nullable(); // Personne de contact principale
            $table->string('status')->default('Active'); // Statut de la société (Active, Inactive, etc.)
            $table->timestamps(); // created_at et updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
