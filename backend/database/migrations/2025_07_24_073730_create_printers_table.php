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
            // Quota mensuel de pages
            $table->integer('total_quota_pages')->default(0)->comment('Quota total de pages autorisées.');

            $table->date('installDate')->nullable();
            $table->dateTime('lastMaintenance')->nullable();
            $table->boolean('is_purchased')->default(true)->comment('Indique si l\'imprimante est achetée (true) ou louée (false).');
            $table->integer('monthly_quota_color_large')->default(0)->comment('Quota mensuel couleur grand format');

            $table->integer('monthly_quota_bw_large')->default(0)->comment('Quota mensuel noir et blanc grand format');

            $table->boolean('is_returned_to_warehouse')->default(false)->comment('Indique si l\'imprimante a été retournée à l\'entrepôt.');
             // Quotas par type d'impression
            $table->integer('monthly_quota_bw')->default(0)->comment('Quota mensuel noir et blanc');
            $table->integer('monthly_quota_color')->default(0)->comment('Quota mensuel couleur');

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
