<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionMessageDeletion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'discussion_message_id',
        'user_id',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(DiscussionMessage::class, 'discussion_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
