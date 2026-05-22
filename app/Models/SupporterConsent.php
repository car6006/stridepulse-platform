<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupporterConsent extends Model
{
    use HasFactory;

    public const STATUS_OPTED_IN = 'opted_in';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $guarded = [];

    protected $casts = [
        'consented_at' => 'datetime',
        'revoked_at' => 'datetime',
        'audit_payload' => 'array',
    ];

    public function supporter()
    {
        return $this->belongsTo(Supporter::class);
    }

    public function invitation()
    {
        return $this->belongsTo(SupporterInvitation::class, 'supporter_invitation_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
