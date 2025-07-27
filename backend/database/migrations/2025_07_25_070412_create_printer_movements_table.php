<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrinterMovementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('printer_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_id')->constrained()->onDelete('cascade'); // Imprimante déplacée
            $table->foreignId('old_department_id')->nullable()->constrained('departments')->onDelete('set null'); // Ancien département
            $table->foreignId('new_department_id')->constrained('departments')->onDelete('cascade'); // Nouveau département
            $table->foreignId('moved_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // Utilisateur qui a effectué le mouvement
            $table->text('notes')->nullable(); // Notes sur le mouvement
            $table->timestamp('date_mouvement')->useCurrent(); // Date du mouvement, par défaut à la date actuelle
            $table->timestamps(); // created_at et updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('printer_movements');
    }
}
