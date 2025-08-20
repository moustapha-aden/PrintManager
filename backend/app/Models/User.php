<?php

namespace App\Models;

use App\Models\Intervention;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        // Utilisez votre notification personnalisée ici
        $this->notify(new CustomResetPasswordNotification($token));
    }

    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_id',
        'roleDisplay',
        'company_id',
        'status',
        'statusDisplay',
        'lastLogin',
        'requestsHandled',
                'phone', // Ajouté pour le téléphone
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Un utilisateur appartient à une société (optionnel)
    public function company(): BelongsTo
    {
        return $this->belongsTo(related: Company::class);
    }

    // Un utilisateur appartient à un département (optionnel)
    public function department(): BelongsTo
    {
        return $this->belongsTo(related: Department::class);
    }

    // Un utilisateur (technicien) peut avoir plusieurs interventions en tant que technician
    public function interventionsAsTechnician(): HasMany
    {
        return $this->hasMany(Intervention::class, 'technician_id');
    }

    // Un utilisateur (client) peut avoir plusieurs interventions en tant que client
    public function interventionsAsClient(): HasMany
    {
        return $this->hasMany(Intervention::class, 'client_id');
    }
}
