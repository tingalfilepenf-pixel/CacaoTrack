<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Variety extends Model
{
    use Archivable;

    protected $table = 'varieties';

    protected $primaryKey = 'variety_id';

    public $timestamps = false;

    protected $fillable = [
        'variety_name',
        'description',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'variety_id', 'variety_id');
    }

    /**
     * Centralized validation rules for Variety master data (RULE 6).
     * Duplicate variety names are allowed (ERD declares no unique constraint).
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'variety_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Display reference for the variety (presentation only, not a DB column).
     */
    public function reference(): string
    {
        return 'VAR-'.str_pad((string) $this->variety_id, 3, '0', STR_PAD_LEFT);
    }
}
