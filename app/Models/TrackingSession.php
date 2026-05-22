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
        'last_movement_at' => 'datetime',
        'last_status_changed_at' => 'datetime',
        'notification_suppressed_at' => 'datetime',
        'notification_state' => 'array',
    ];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function raceEntry()
    {
        return $this->belongsTo(RaceEntry::class);
    }

    public function telemetryPoints()
    {
        return $this->hasMany(TelemetryPoint::class);
    }

    public function liveTrackInboundMessages()
    {
        return $this->hasMany(LiveTrackInboundMessage::class);
    }

    public function athleteActivity()
    {
        return $this->hasOne(AthleteActivity::class);
    }

    public function whatsappDispatches()
    {
        return $this->hasMany(WhatsAppMessageDispatch::class);
    }

    public function telemetryAlerts()
    {
        return $this->hasMany(TelemetryAlert::class);
    }

    public function eventEstimations()
    {
        return $this->hasMany(EventEstimation::class);
    }
}
