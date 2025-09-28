<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrinterQuota extends Model
{
    use HasFactory;
     protected $fillable = [
        'printer_id',
        'monthly_quota_bw',
        'monthly_quota_color',
        'total_quota',
        'depassementColor',
        'depassementBW',
        'date_prelevement',
        'monthly_quota_color_large',
        'monthly_quota_bw_large',
        'mois',
    ];
     protected $casts = [
        'date_prelevement' => 'date',
        'mois' => 'date',
    ];

    // Relation avec l'imprimante
    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }


}
