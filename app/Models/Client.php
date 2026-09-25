<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use Archivable;

    protected $table = 'clients';

    protected $primaryKey = 'client_id';

    public $timestamps = false;

    protected $fillable = [
        'client_name',
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

    public function stockOuts(): HasMany
    {
        return $this->hasMany(StockOut::class, 'client_id', 'client_id');
    }

    /**
     * Centralized validation rules for Client master data (RULE 6).
     * Duplicate client names are allowed (ERD declares no unique constraint).
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'client_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Display reference for the client (presentation only, not a DB column).
     */
    public function reference(): string
    {
        return 'CLI-'.str_pad((string) $this->client_id, 3, '0', STR_PAD_LEFT);
    }
}
