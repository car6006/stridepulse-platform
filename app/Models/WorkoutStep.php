<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutStep extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'metadata' => 'array',
    ];
    public function workout() { return $this->belongsTo(Workout::class); }
}
