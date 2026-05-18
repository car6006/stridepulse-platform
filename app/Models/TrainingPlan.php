<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingPlan extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'uuid' => 'string',
        'metadata' => 'array',
    ];
    public function coach() { return $this->belongsTo(Coach::class); }
    public function items() { return $this->hasMany(TrainingPlanItem::class); }
}
