<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PotType extends Model
{
    use Archivable;

    protected $table = 'pot_types';

    protected $primaryKey = 'pot_id';

    public $timestamps = false;

    protected $fillable = [
        'pot_name',
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
        return $this->hasMany(Batch::class, 'pot_id', 'pot_id');
    }

    /**
     * Centralized validation rules for Pot Type master data (RULE 6).
     * Duplicate pot type names are allowed (ERD declares no unique constraint).
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'pot_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Display reference for the pot type (presentation only, not a DB column).
     */
    public function reference(): string
    {
        return 'POT-'.str_pad((string) $this->pot_id, 3, '0', STR_PAD_LEFT);
    }
}
