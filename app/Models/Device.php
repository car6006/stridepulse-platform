<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    public const STATUS_UNCLAIMED = 'unclaimed';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_READY = 'ready';

    public const STATUS_LIVE = 'live';

    public const STATUS_OFFLINE = 'offline';

    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = [];

    protected $casts = [
        'uuid' => 'string',
        'device_uuid' => 'string',
        'last_seen_at' => 'datetime',
        'last_claimed_at' => 'datetime',
        'last_telemetry_at' => 'datetime',
        'archived_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getPairingCodeAttribute(): ?string
    {
        $storedPairingCode = $this->attributes['pairing_code'] ?? ($this->metadata['pairing_code'] ?? null);

        if (! empty($storedPairingCode)) {
            return strtoupper((string) $storedPairingCode);
        }

        if ($this->provider !== 'garmin') {
            return null;
        }

        $deviceUuid = $this->device_uuid ?? $this->uuid;

        if (empty($deviceUuid)) {
            return null;
        }

        return self::derivePairingCode($deviceUuid);
    }

    public function pairingCode(): ?string
    {
        return $this->pairing_code;
    }

    public static function derivePairingCode(string $deviceUuid): string
    {
        $alphanumeric = preg_replace('/[^A-HJ-NP-Za-km-z2-9]/', '', $deviceUuid);

        return strtoupper(substr($alphanumeric, -6));
    }

    public function isClaimedForLifecycle(): bool
    {
        return in_array($this->status, [
            self::STATUS_CLAIMED,
            self::STATUS_READY,
            self::STATUS_LIVE,
            self::STATUS_OFFLINE,
            'active',
        ], true);
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED || $this->archived_at !== null;
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function trackingSessions()
    {
        return $this->hasMany(TrackingSession::class);
    }
}
