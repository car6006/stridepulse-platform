<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventEstimation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'estimated_finish_at' => 'datetime',
        'payload' => 'array',
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
