<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'action',
        'module',
        'ip_address',
        'user_agent',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper static method to log audit events easily.
     */
    public static function record(?int $userId, string $eventType, string $action, ?string $module = null, ?array $payload = null): self
    {
        if (!$userId && Auth::check()) {
            $userId = Auth::id();
        }

        $tenantId = null;
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                $tenantId = $user->tenant_id;
            }
        } elseif (Auth::check()) {
            $tenantId = Auth::user()->tenant_id;
        }

        return self::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'action' => $action,
            'module' => $module,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'payload' => $payload,
        ]);
    }
}
