<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // Importez HasMany

class Company extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'address',
        'country',        // Ajouté selon votre migration
        'phone',
        'email',
        'contact_person', // Ajouté selon votre migration
        'status',
        'is_returned_to_warehouse',
        'quota_BW',
        'quota_Color',
        'quota_monthly',
    ];

    /**
     * Get the departments for the company.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Get the users for the company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the printers for the company.
     */
    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }
}
