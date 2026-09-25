<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use Archivable;

    protected $table = 'suppliers';

    protected $primaryKey = 'supplier_id';

    public $timestamps = false;

    protected $fillable = [
        'supplier_name',
        'contact_number',
        'address',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function stockIns(): HasMany
    {
        return $this->hasMany(StockIn::class, 'supplier_id', 'supplier_id');
    }

    /**
     * Centralized validation rules for Supplier master data (RULE 6).
     * Duplicate supplier names are allowed (ERD declares no unique constraint).
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'supplier_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Display reference for the supplier (presentation only, not a DB column).
     */
    public function reference(): string
    {
        return 'SUP-'.str_pad((string) $this->supplier_id, 3, '0', STR_PAD_LEFT);
    }
}
