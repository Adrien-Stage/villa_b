<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = ['position', 'booked_on', 'reference', 'label', 'amount', 'journal_entry_line_id'];

    protected $casts = ['booked_on' => 'date', 'amount' => 'integer'];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function ledgerLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'journal_entry_line_id');
    }
}
