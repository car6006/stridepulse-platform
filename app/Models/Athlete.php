<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Athlete extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'uuid' => 'string',
        'metadata' => 'array',
    ];

    public function coaches()
    {
        return $this->belongsToMany(Coach::class, 'coach_athlete');
    }

    public function workouts()
    {
        return $this->hasMany(Workout::class);
    }

    public function raceEntries()
    {
        return $this->hasMany(RaceEntry::class);
    }

    public function trackingSessions()
    {
        return $this->hasMany(TrackingSession::class);
    }

    public function athleteActivities()
    {
        return $this->hasMany(AthleteActivity::class);
    }

    public function garminConnections()
    {
        return $this->hasMany(GarminConnection::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function whatsappConversations()
    {
        return $this->hasMany(WhatsAppConversation::class);
    }

    public function eventFollowers()
    {
        return $this->hasMany(EventFollower::class);
    }
}
