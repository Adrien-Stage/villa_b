<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerImport extends Model
{
    protected $fillable = ['kind', 'reference', 'digest', 'imported_by'];
}
