<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveTrackInboundMessage extends Model
{
    use HasFactory;

    protected $table = 'livetrack_inbound_messages';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function trackingSession()
    {
        return $this->belongsTo(TrackingSession::class);
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }
}
