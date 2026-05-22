<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelemetryAlert extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'triggered_at' => 'datetime',
    ];

    public function trackingSession()
    {
        return $this->belongsTo(TrackingSession::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
