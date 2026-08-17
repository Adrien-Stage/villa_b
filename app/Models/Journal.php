<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Journal comptable : ventes, achats, banque, caisse, opérations diverses. */
class Journal extends Model
{
    public const SALES = 'VT';
    public const PURCHASES = 'AC';
    public const BANK = 'BQ';
    public const CASH = 'CA';
    public const MISC = 'OD';

    protected $fillable = ['code', 'label', 'default_account', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public static function byCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }
}
