<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupporterInvitation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function trackingSession()
    {
        return $this->belongsTo(TrackingSession::class);
    }

    public function supporter()
    {
        return $this->belongsTo(Supporter::class);
    }
}
