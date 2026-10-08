<?php

namespace Nsd7\AiMeter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static float cost(string $provider, string $model, \Nsd7\AiMeter\Core\Data\TokenUsage $usage)
 * @method static \Nsd7\AiMeter\Core\Data\LlmCall record(\Nsd7\AiMeter\Core\Data\LlmCall $call)
 * @method static \Nsd7\AiMeter\Core\Data\LlmCall log(array $attributes)
 * @method static \Nsd7\AiMeter\Core\Data\LlmCall recordPrism(object $response, array $attributes = [])
 * @method static \Nsd7\AiMeter\Runs\MeterRun run(\Nsd7\AiMeter\Core\Budget\BudgetScope $scope, array $options = [])
 * @method static float spent(\Nsd7\AiMeter\Core\Budget\BudgetScope $scope, \Nsd7\AiMeter\Core\Budget\Period $period)
 * @method static \Nsd7\AiMeter\Core\Budget\BudgetDecision check(\Nsd7\AiMeter\Core\Budget\BudgetScope $scope, float $additionalUsd = 0.0)
 * @method static float|null remaining(\Nsd7\AiMeter\Core\Budget\BudgetScope $scope)
 *
 * @see \Nsd7\AiMeter\Meter
 */
class Meter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Nsd7\AiMeter\Meter::class;
    }
}
