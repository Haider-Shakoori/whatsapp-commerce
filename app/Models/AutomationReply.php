<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationReply extends Model
{
    protected $fillable = [
        'merchant_id', 'conversation_id', 'source_message_id', 'rule_type', 'outbound_message_id',
    ];
}
