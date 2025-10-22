<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoiceService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cpe_device_id',
        'service_instance',
        'service_name',
        'service_type',
        'enabled',
        'protocol',
        'max_profiles',
        'max_lines',
        'max_sessions',
        'capabilities',
        'codecs',
        'rtp_dscp',
        'rtp_port_min',
        'rtp_port_max',
        'stun_enabled',
        'stun_server',
        'stun_port',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'stun_enabled' => 'boolean',
        'capabilities' => 'array',
        'codecs' => 'array',
        'max_profiles' => 'integer',
        'max_lines' => 'integer',
        'max_sessions' => 'integer',
    ];

    public function cpeDevice(): BelongsTo
    {
        return $this->belongsTo(CpeDevice::class);
    }

    public function sipProfiles(): HasMany
    {
        return $this->hasMany(SipProfile::class);
    }

    public function getAvailableCodecsAttribute(): array
    {
        return $this->codecs ?? [
            'G.711',
            'G.729',
            'G.722',
            'G.726',
            'Opus',
            'AMR',
            'iLBC'
        ];
    }
}
