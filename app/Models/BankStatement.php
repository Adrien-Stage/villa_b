<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends Model
{
    protected $fillable = ['account_code', 'reference', 'digest', 'imported_by'];

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
