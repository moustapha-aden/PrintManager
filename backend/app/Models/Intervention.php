<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intervention extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'client_id',
        'technician_id',
        'printer_id',
        'status',
        'description',
        'priority',
        'intervention_type',
    ];
protected $casts = [
        'start_date' => 'datetime', // Convertira automatiquement en Carbon et gérera le format
        'end_date' => 'datetime',   // Convertira automatiquement en Carbon et gérera le format
    ];
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
}
