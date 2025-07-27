<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrinterMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'printer_id',
        'old_department_id',
        'new_department_id',
        'moved_by_user_id',
        'date_mouvement',
        'notes',
    ];

    /**
     * Get the printer associated with the movement.
     */
    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    /**
     * Get the old department for the movement.
     */
    public function oldDepartment()
    {
        return $this->belongsTo(Department::class, 'old_department_id')->with('company');
    }

    /**
     * Get the new department for the movement.
     */
    public function newDepartment()
    {
        return $this->belongsTo(Department::class, 'new_department_id')->with('company');
    }

    /**
     * Get the user who moved the printer.
     */
    public function movedBy()
    {
        return $this->belongsTo(User::class, 'moved_by_user_id');
    }
}
