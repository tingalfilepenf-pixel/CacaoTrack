<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Analytic extends Model
{
    protected $table = 'analytics';

    protected $primaryKey = 'analytics_id';

    public $timestamps = false;

    /**
     * Supported analytics/report types. Kept in one place so validation, the
     * generator and the views all agree (RULE 6).
     */
    public const TYPE_OPERATIONAL = 'operational';

    public const TYPE_STOCK_LEDGER = 'stock_ledger';

    public const TYPE_MORTALITY_MATRIX = 'mortality_matrix';

    public const TYPE_SEASONALITY = 'seasonality';

    public const TYPE_AGE_PROFILE = 'age_profile';

    public const TYPE_GAP_REPORT = 'gap_report';

    public const TYPES = [
        self::TYPE_OPERATIONAL,
        self::TYPE_STOCK_LEDGER,
        self::TYPE_MORTALITY_MATRIX,
        self::TYPE_SEASONALITY,
        self::TYPE_AGE_PROFILE,
        self::TYPE_GAP_REPORT,
    ];

    protected $fillable = [
        'user_id',
        'analysis_type',
        'analysis_period',
        'result_summary',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'result_summary' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_OPERATIONAL => 'Operational Analytics',
            self::TYPE_STOCK_LEDGER => 'Cacao Tree Stock Ledger',
            self::TYPE_MORTALITY_MATRIX => 'Mortality Root-Cause Matrix',
            self::TYPE_SEASONALITY => 'Mortality Seasonality Heatmap',
            self::TYPE_AGE_PROFILE => 'Age Profile',
            self::TYPE_GAP_REPORT => 'Gap Report',
            default => $type,
        };
    }

    public function typeLabelText(): string
    {
        return self::typeLabel($this->analysis_type);
    }

    public static function rules(): array
    {
        return [
            'analysis_type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'scion_variety_id' => ['nullable', 'integer', 'exists:scion_varieties,scion_variety_id'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,batch_id'],
        ];
    }
}
