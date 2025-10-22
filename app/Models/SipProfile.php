<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SipProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'voice_service_id',
        'profile_instance',
        'enabled',
        'profile_name',
        'proxy_server',
        'proxy_port',
        'registrar_server',
        'registrar_port',
        'outbound_proxy',
        'outbound_proxy_port',
        'auth_username',
        'auth_password',
        'domain',
        'realm',
        'transport_protocol',
        'register_expires',
        'register_retry',
        'register_retry_interval',
        'codec_list',
        'packetization_period',
        'silence_suppression',
        'dtmf_method',
        'dtmf_payload_type',
        'sip_dscp',
        'vlan_id',
        'vlan_priority',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'register_retry' => 'boolean',
        'silence_suppression' => 'boolean',
        'codec_list' => 'array',
    ];

    protected $hidden = [
        'auth_password',
    ];

    public function voiceService(): BelongsTo
    {
        return $this->belongsTo(VoiceService::class);
    }

    public function voipLines(): HasMany
    {
        return $this->hasMany(VoipLine::class);
    }
}
