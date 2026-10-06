<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une personne planifiée sur un quart, un jour donné, par son chef de service. */
class ShiftAssignment extends Model
{
    protected $fillable = ['user_id', 'work_shift_id', 'department_id', 'date', 'assigned_by'];

    // « date » reste une chaîne AAAA-MM-JJ : un cast de date y ajouterait une
    // heure sous SQLite, et la date ne se comparerait plus à elle-même.

    /** Jour où le quart commence. */
    public function jour(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date)->startOfDay();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function debut(): CarbonImmutable
    {
        return $this->shift->debutLe($this->jour());
    }

    public function fin(): CarbonImmutable
    {
        return $this->shift->finLe($this->jour());
    }

    /** Le quart est-il en cours à cet instant ? */
    public function enCoursA(CarbonImmutable $instant): bool
    {
        return $instant->greaterThanOrEqualTo($this->debut()) && $instant->lessThan($this->fin());
    }
}
