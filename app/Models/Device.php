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
        'device_uuid' => 'string',
        'last_seen_at' => 'datetime',
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

    public static function derivePairingCode(string $deviceUuid): string
    {
        $alphanumeric = preg_replace('/[^A-Za-z0-9]/', '', $deviceUuid);

        return strtoupper(substr($alphanumeric, -6));
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
