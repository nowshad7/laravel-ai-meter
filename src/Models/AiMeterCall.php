<?php

namespace Nsd7\AiMeter\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $model
 * @property float $cost_usd
 * @property int $total_tokens
 */
class AiMeterCall extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'prompt_tokens' => 'int',
        'completion_tokens' => 'int',
        'total_tokens' => 'int',
        'cached_tokens' => 'int',
        'reasoning_tokens' => 'int',
        'cost_usd' => 'float',
        'cost_display' => 'float',
        'latency_ms' => 'int',
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('ai-meter.table', 'ai_meter_calls');
    }
}
