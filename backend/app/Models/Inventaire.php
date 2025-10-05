<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventaire extends Model
{
    use HasFactory;
    protected $fillable = [
        'materiel_id',
        'quantite',
        'printer_id',
        'date_deplacement',
    ];
    public function materiel()
    {
        return $this->belongsTo(Materielle::class, 'materiel_id');
    }

    public function printer()
    {
        return $this->belongsTo(Printer::class, 'printer_id');
    }
}
