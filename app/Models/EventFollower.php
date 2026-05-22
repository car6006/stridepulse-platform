<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventFollower extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'opted_in_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function supporter()
    {
        return $this->belongsTo(Supporter::class);
    }

    public function consent()
    {
        return $this->belongsTo(SupporterConsent::class, 'supporter_consent_id');
    }
}
