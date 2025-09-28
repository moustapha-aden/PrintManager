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
        Schema::create('printer_quotas', function (Blueprint $table) {
           $table->id();
            $table->foreignId('printer_id')->constrained('printers')->onDelete('cascade');

            // Quotas par type d'impression noir et blanc
            $table->integer('monthly_quota_bw')->default(0)->comment('Quota mensuel noir et blanc');
            $table->integer('monthly_quota_bw_large')->default(0)->comment('Quota mensuel noir et blanc grand format');

            // Quotas par type d'impression couleur
            $table->integer('monthly_quota_color')->default(0)->comment('Quota mensuel couleur');
            $table->integer('monthly_quota_color_large')->default(0)->comment('Quota mensuel couleur grand format');

             // Quotas par type d'impression
            $table->integer('depassementBW')->default(0)->comment('Dépassement du quota noir et blanc');
            $table->integer('depassementColor')->default(0)->comment('Dépassement du quota couleur');
            $table->integer('total_quota')->default(0)->comment('Quota total');
            $table->date('date_prelevement')->comment('Date de prelevement');
            $table->date('mois')->comment('mois de relevement');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printer_quotas');
    }
};
