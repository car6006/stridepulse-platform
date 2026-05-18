<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sport extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'name' => 'string',
    ];
    public function workouts() { return $this->hasMany(Workout::class); }
}
