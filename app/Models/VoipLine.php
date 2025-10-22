<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoipLine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sip_profile_id',
        'line_instance',
        'enabled',
        'directory_number',
        'display_name',
        'sip_uri',
        'auth_username',
        'auth_password',
        'status',
        'call_state',
        'last_registration',
        'call_waiting_enabled',
        'call_forward_enabled',
        'call_forward_number',
        'call_forward_on_busy',
        'call_forward_on_no_answer',
        'call_forward_no_answer_timeout',
        'dnd_enabled',
        'caller_id_enable',
        'caller_id_name',
        'anonymous_call_rejection',
        'phy_interface',
        'incoming_calls_received',
        'incoming_calls_answered',
        'incoming_calls_failed',
        'outgoing_calls_attempted',
        'outgoing_calls_answered',
        'outgoing_calls_failed',
        'total_call_time',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'call_waiting_enabled' => 'boolean',
        'call_forward_enabled' => 'boolean',
        'call_forward_on_busy' => 'boolean',
        'call_forward_on_no_answer' => 'boolean',
        'dnd_enabled' => 'boolean',
        'caller_id_enable' => 'boolean',
        'anonymous_call_rejection' => 'boolean',
        'last_registration' => 'datetime',
    ];

    protected $hidden = [
        'auth_password',
    ];

    protected $appends = ['line_number'];

    public function sipProfile(): BelongsTo
    {
        return $this->belongsTo(SipProfile::class);
    }

    public function getLineNumberAttribute(): ?int
    {
        return $this->line_instance;
    }

    public function getCallSuccessRateAttribute(): float
    {
        $totalCalls = $this->outgoing_calls_attempted + $this->incoming_calls_received;
        if ($totalCalls === 0) {
            return 0;
        }
        
        $successfulCalls = $this->outgoing_calls_answered + $this->incoming_calls_answered;
        return round(($successfulCalls / $totalCalls) * 100, 2);
    }
}
