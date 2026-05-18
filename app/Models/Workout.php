<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workout extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'uuid' => 'string',
        'metadata' => 'array',
    ];
    public function athlete() { return $this->belongsTo(Athlete::class); }
    public function sport() { return $this->belongsTo(Sport::class); }
    public function steps() { return $this->hasMany(WorkoutStep::class); }
}
