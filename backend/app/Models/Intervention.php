<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Intervention extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'numero_demande',
        'client_id',
        'technician_id',
        'printer_id',
        'status',
        'description',
        'priority',
        'solution',
        'intervention_type',
        'date_previsionnelle',
        'image_path', // Assurez-vous que 'image_path' est bien dans fillable
        'start_date_intervention', // Ajoutez ce champ si nécessaire
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'start_date_intervention' => 'datetime',
        'date_previsionnelle' => 'datetime', // Ajoutez ceci si vous voulez le caster aussi
    ];

    // Relations
    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    // Alias pour la clarté, si vous préférez ces noms
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'client_id');
    }



    public function getImageUrlAttribute()
    {

        if ($this->image_path) {
            // Utilise Storage::url() pour générer une URL publique pour le fichier
            return Storage::url($this->image_path);
        }
        return null; // Retourne null si aucun chemin d'image n'est défini
    }


    public function setImagePathAttribute($value)
    {
        // Si une nouvelle image est fournie et qu'elle est une instance de UploadedFile
        if ($value instanceof \Illuminate\Http\UploadedFile) {
            // Supprimer l'ancienne image si elle existe
            if ($this->image_path) {
                Storage::delete($this->image_path);
            }
            // Stocke la nouvelle image et enregistre son chemin
            $this->attributes['image_path'] = $value->store('interventions', 'public'); // 'interventions' est le dossier, 'public' est le disque
        } elseif (is_null($value)) {
            // Si la valeur est null, cela signifie que l'image a été supprimée ou n'existe pas
            if ($this->image_path) {
                Storage::delete($this->image_path);
            }
            $this->attributes['image_path'] = null;
        } else {
            // Si ce n'est pas un fichier, conserve la valeur existante (par exemple, si c'est déjà un chemin de chaîne)
            $this->attributes['image_path'] = $value;
        }
    }
}
