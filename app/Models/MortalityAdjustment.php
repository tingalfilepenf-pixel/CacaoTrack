<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MortalityAdjustment extends Model
{
    protected $table = 'mortality_adjustments';

    protected $primaryKey = 'adjustment_id';

    public $timestamps = false;

    protected $fillable = [
        'mortality_id',
        'approved_by_id',
        'adjustment_quantity',
        'reason',
        'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'adjusted_at' => 'datetime',
        ];
    }

    public function mortalityRecord(): BelongsTo
    {
        return $this->belongsTo(MortalityRecord::class, 'mortality_id', 'mortality_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
