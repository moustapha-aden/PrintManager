<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_id',
    ];

    // Un département appartient à une société (Company)
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Un département a plusieurs imprimantes (Printers)
    public function printers()
    {
        return $this->hasMany(Printer::class);
    }
}
