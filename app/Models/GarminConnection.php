<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GarminConnection extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'metadata' => 'array',
    ];
    public function athlete() { return $this->belongsTo(Athlete::class); }
}
