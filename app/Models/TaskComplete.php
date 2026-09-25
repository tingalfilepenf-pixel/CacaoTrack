<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComplete extends Model
{
    protected $table = 'task_completes';

    protected $primaryKey = 'completion_id';

    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'user_id',
        'verified_by_id',
        'completion_date',
        'verified_at',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'completion_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ActivityTask::class, 'task_id', 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }
}
