<?php
namespace App\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    use HasFactory;

    protected $fillable = [
        'model',
        'brand',
        'serial',
        'status',
        'statusDisplay',
        'company_id',
        'department_id',
        'installDate',
        'is_purchased',
        'lastMaintenance',
        'is_returned_to_warehouse',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function interventions()
    {
        return $this->hasMany(Intervention::class);
    }
}
