<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AthleteActivity extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'uuid' => 'string',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'distance_m' => 'decimal:2',
        'ascent_m' => 'decimal:2',
        'descent_m' => 'decimal:2',
        'start_latitude' => 'decimal:7',
        'start_longitude' => 'decimal:7',
        'end_latitude' => 'decimal:7',
        'end_longitude' => 'decimal:7',
        'summary_payload' => 'array',
    ];

    public function athlete() { return $this->belongsTo(Athlete::class); }
    public function trackingSession() { return $this->belongsTo(TrackingSession::class); }
    public function sport() { return $this->belongsTo(Sport::class); }
}
