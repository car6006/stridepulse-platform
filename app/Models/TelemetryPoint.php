<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelemetryPoint extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'raw_payload' => 'array',
        'metadata' => 'array',
        'recorded_at' => 'datetime',
        'distance_m' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'altitude_m' => 'decimal:2',
        'heading_degrees' => 'decimal:2',
        'ascent_m' => 'decimal:2',
        'descent_m' => 'decimal:2',
        'current_speed_mps' => 'decimal:3',
    ];
    public function trackingSession() { return $this->belongsTo(TrackingSession::class); }
}
