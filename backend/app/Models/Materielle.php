<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materielle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'reference',
        'type',
        'quantite',
        'sortie',
    ];
    public function inventaires()
{
    return $this->hasMany(Inventaire::class, 'materiel_id');
}

}
