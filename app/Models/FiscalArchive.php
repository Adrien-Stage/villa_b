<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalArchive extends Model
{
    protected $fillable = ['fiscal_year_id', 'regime', 'sha256', 'payload', 'retain_until', 'sealed_by'];

    protected $hidden = ['payload'];

    protected $casts = ['retain_until' => 'date'];
}
