<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
    ];

    protected $casts = [
        'deleted_for_all_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DiscussionConversation::class, 'conversation_id');
    }

    public function deletions(): HasMany
    {
        return $this->hasMany(DiscussionMessageDeletion::class, 'discussion_message_id');
    }

    public function deletedForAllBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_for_all_by');
    }
}
