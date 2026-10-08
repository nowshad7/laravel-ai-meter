<?php

namespace Nsd7\AiMeter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $run_id
 * @property string $status
 * @property int $step_count
 * @property int $tool_call_count
 * @property float $cost_usd
 */
class AiMeterRun extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'step_count' => 'int',
        'tool_call_count' => 'int',
        'cost_usd' => 'float',
        'budget_usd' => 'float',
        'max_tool_calls' => 'int',
        'max_wall_clock' => 'int',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('ai-meter.runs.table', 'ai_meter_runs');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(AiMeterCall::class, 'run_id', 'run_id');
    }
}
