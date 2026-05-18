<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coach extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'uuid' => 'string',
        'metadata' => 'array',
    ];
    public function athletes() { return $this->belongsToMany(Athlete::class, 'coach_athlete'); }
    public function trainingPlans() { return $this->hasMany(TrainingPlan::class); }
}
