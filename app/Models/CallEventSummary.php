<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallEventSummary extends Model
{
    protected $table = 'call_event_summaries';

    protected $fillable = [
        'conversation_space_id',
        'account_key',
        'organization_id',
        'account_name',
        'call_created',
        'call_answered',
        'call_ended',
        'duration_ms',
        'direction',
        'caller_outcome',
        'call_initiator',
        'caller_number',
        'caller_name',
        'call_provider',
        'participants',
        'raw',
    ];

    protected $casts = [
        'call_created'  => 'datetime',
        'call_answered' => 'datetime',
        'call_ended'    => 'datetime',
        'duration_ms'   => 'integer',
        'raw'           => 'array',
    ];
}
