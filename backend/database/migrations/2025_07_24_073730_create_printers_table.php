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
        Schema::create('printers', function (Blueprint $table) {
            $table->id(); // Auto-incrémenté, clé primaire
            $table->string('model');
            $table->string('brand');
            $table->string('serial')->unique(); // Numéro de série unique
            $table->string('status')->default('active'); // active, maintenance, hors-service
            $table->string('statusDisplay')->default('active'); // "Active", "En maintenance", "Hors service"
            $table->foreignId('company_id')->constrained('companies'); // ID de la société, clé étrangère
            $table->foreignId('department_id')->constrained('departments'); // ID du département, clé étrangère
            $table->date('installDate')->nullable();
            $table->dateTime('lastMaintenance')->nullable();
            $table->timestamps(); // created_at et updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
