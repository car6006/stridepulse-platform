<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConversation extends Model
{
    use HasFactory;

    public const STATE_IDLE = 'idle';

    public const STATE_AWAITING_EVENT_NAME = 'awaiting_event_name';

    public const STATE_AWAITING_EVENT_DATE = 'awaiting_event_date';

    public const STATE_AWAITING_EVENT_DISTANCE = 'awaiting_event_distance';

    public const STATE_AWAITING_EVENT_TYPE = 'awaiting_event_type';

    public const STATE_AWAITING_SUPPORTERS = 'awaiting_supporters';

    public const STATE_AWAITING_CONSENT = 'awaiting_consent';

    public const STATE_SESSION_ARMED = 'session_armed';

    protected $table = 'whatsapp_conversations';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
    ];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class);
    }
}
