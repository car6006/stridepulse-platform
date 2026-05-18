<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaceEntry extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'uuid' => 'string',
        'metadata' => 'array',
    ];
    public function event() { return $this->belongsTo(Event::class); }
    public function athlete() { return $this->belongsTo(Athlete::class); }
    public function trackingSessions() { return $this->hasMany(TrackingSession::class); }
}
