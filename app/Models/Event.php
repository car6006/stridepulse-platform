<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'uuid' => 'string',
        'metadata' => 'array',
    ];

    public function raceEntries()
    {
        return $this->hasMany(RaceEntry::class);
    }

    public function supporterInvitations()
    {
        return $this->hasMany(SupporterInvitation::class);
    }

    public function followers()
    {
        return $this->hasMany(EventFollower::class);
    }

    public function checkpoints()
    {
        return $this->hasMany(EventCheckpoint::class);
    }
}
