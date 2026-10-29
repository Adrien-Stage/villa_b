<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionConversation extends Model
{
    // Effacé par un bouton « Supprimer », il passe par la corbeille.
    use \App\Models\Concerns\VaALaCorbeille;

    use HasFactory;

    protected $fillable = [
        'title',
        'is_group',
        'created_by',
    ];

    protected $casts = [
        'is_group' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'discussion_conversation_user')
            ->withPivot('last_read_at', 'archived_at', 'deleted_at', 'is_admin')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DiscussionMessage::class, 'conversation_id');
    }

    /** Ce que la corbeille affiche pour cette fiche. */
    public function libelleCorbeille(): string
    {
        return $this->title ?: 'Conversation #' . $this->id;
    }
}
