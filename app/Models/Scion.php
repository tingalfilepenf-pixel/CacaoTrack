<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Scion extends Model
{
    use Archivable;

    protected $table = 'scions';

    protected $primaryKey = 'scion_id';

    public $timestamps = false;

    protected $fillable = [
        'scion_name',
        'description',
        'quantity',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'scion_id', 'scion_id');
    }

    /**
     * Credit received scion stock (the Receive Scions action).
     */
    public function receive(int $quantity): void
    {
        $this->update(['quantity' => (int) $this->quantity + $quantity]);
    }

    /**
     * Debit used scion stock (replanting/re-grafting). The caller validates the
     * quantity is available and applies this inside a DB transaction (RULE 7:
     * the balance must never go negative).
     */
    public function deduct(int $quantity): void
    {
        if ($quantity > (int) $this->quantity) {
            throw new InvalidArgumentException('Insufficient scion stock.');
        }

        $this->update(['quantity' => (int) $this->quantity - $quantity]);
    }

    /**
     * Display reference for the scion pool (presentation only, not a DB column).
     */
    public function reference(): string
    {
        return 'SCN-'.str_pad((string) $this->scion_id, 3, '0', STR_PAD_LEFT);
    }
}
