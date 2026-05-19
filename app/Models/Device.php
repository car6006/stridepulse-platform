<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'uuid' => 'string',
        'last_seen_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function athlete() { return $this->belongsTo(Athlete::class); }
    public function trackingSessions() { return $this->hasMany(TrackingSession::class); }
}
