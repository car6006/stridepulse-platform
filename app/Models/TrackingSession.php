<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingSession extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'uuid' => 'string',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'ended_at' => 'datetime',
        'livetrack_received_at' => 'datetime',
        'last_direct_telemetry_at' => 'datetime',
    ];
    public function athlete() { return $this->belongsTo(Athlete::class); }
    public function sport() { return $this->belongsTo(Sport::class); }
    public function raceEntry() { return $this->belongsTo(RaceEntry::class); }
    public function telemetryPoints() { return $this->hasMany(TelemetryPoint::class); }
    public function liveTrackInboundMessages() { return $this->hasMany(LiveTrackInboundMessage::class); }
    public function athleteActivity() { return $this->hasOne(AthleteActivity::class); }
}
