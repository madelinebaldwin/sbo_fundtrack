<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_year_id', 'created_by', 'name', 'description', 'event_date', 'starts_at', 'ends_at', 'venue', 'status', 'contribution_target'])]
class Event extends Model
{
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'contribution_target' => 'decimal:2',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
