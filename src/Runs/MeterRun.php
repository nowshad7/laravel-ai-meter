<?php

namespace Nsd7\AiMeter\Runs;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Nsd7\AiMeter\Budget\Exceptions\BudgetExceededException;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Data\LlmCall;
use Nsd7\AiMeter\Meter;
use Nsd7\AiMeter\Models\AiMeterRun;
use Nsd7\AiMeter\Runs\Exceptions\RunLimitExceededException;

/**
 * A single agent run: groups calls under one run_id, tracks step/tool-call/cost
 * aggregates, and enforces per-run caps — the runaway-loop guard.
 *
 *   $run = Meter::run(BudgetScope::user($id), ['budget' => 2.00, 'max_tool_calls' => 10]);
 *   foreach ($steps as $step) {
 *       $run->guard();                 // throws before the next expensive step
 *       $run->recordPrism($response);  // or ->record([...])
 *   }
 *   $run->finish();
 */
class MeterRun
{
    public readonly string $runId;

    protected AiMeterRun $row;

    protected float $startedAt;

    public function __construct(
        protected Meter $meter,
        protected BudgetScope $scope,
        protected ?float $budgetUsd = null,
        protected ?int $maxToolCalls = null,
        protected ?int $maxWallClock = null,
        ?string $label = null,
        ?string $runId = null,
    ) {
        $this->runId = $runId ?: (string) Str::uuid();
        $this->startedAt = microtime(true);

        $this->row = AiMeterRun::create([
            'run_id' => $this->runId,
            'label' => $label,
            'status' => 'running',
            'scope_type' => $scope->type,
            'scope_id' => $scope->id !== null ? (string) $scope->id : null,
            'budget_usd' => $budgetUsd,
            'max_tool_calls' => $maxToolCalls,
            'max_wall_clock' => $maxWallClock,
            'started_at' => Carbon::now(),
        ]);
    }

    public function id(): string
    {
        return $this->runId;
    }

    /**
     * Throw if this run has hit a cap or a budget. Call before each step.
     *
     * @throws RunLimitExceededException|BudgetExceededException
     */
    public function guard(float $estimatedUsd = 0.0): void
    {
        if ($this->maxWallClock !== null && (microtime(true) - $this->startedAt) >= $this->maxWallClock) {
            throw RunLimitExceededException::wallClock($this->maxWallClock);
        }

        if ($this->maxToolCalls !== null && $this->row->tool_call_count >= $this->maxToolCalls) {
            throw RunLimitExceededException::toolCalls($this->maxToolCalls);
        }

        if ($this->budgetUsd !== null && ($this->row->cost_usd + $estimatedUsd) >= $this->budgetUsd) {
            throw new BudgetExceededException(null, 'AI run budget of $' . $this->budgetUsd . ' exceeded.');
        }

        $decision = $this->meter->check($this->scope, $estimatedUsd);

        if (! $decision->allowed) {
            throw new BudgetExceededException($decision);
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function record(array $attributes): LlmCall
    {
        $call = $this->meter->log($this->withRun($attributes));
        $this->tally($call);

        return $call;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function recordPrism(object $response, array $attributes = []): LlmCall
    {
        $call = $this->meter->recordPrism($response, $this->withRun($attributes));
        $this->tally($call);

        return $call;
    }

    public function cost(): float
    {
        return (float) $this->row->cost_usd;
    }

    public function steps(): int
    {
        return (int) $this->row->step_count;
    }

    public function toolCalls(): int
    {
        return (int) $this->row->tool_call_count;
    }

    public function remaining(): ?float
    {
        return $this->budgetUsd === null ? null : max(0.0, $this->budgetUsd - $this->row->cost_usd);
    }

    public function finish(string $status = 'completed'): AiMeterRun
    {
        $this->row->status = $status;
        $this->row->ended_at = Carbon::now();
        $this->row->save();

        return $this->row;
    }

    public function fail(string $error): AiMeterRun
    {
        $this->row->status = 'failed';
        $this->row->error = $error;
        $this->row->ended_at = Carbon::now();
        $this->row->save();

        return $this->row;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function withRun(array $attributes): array
    {
        return array_merge($attributes, ['run_id' => $this->runId, 'scope' => $this->scope]);
    }

    protected function tally(LlmCall $call): void
    {
        $this->row->step_count += 1;

        if ($call->toolName !== null && $call->toolName !== '') {
            $this->row->tool_call_count += 1;
        }

        $this->row->cost_usd = round($this->row->cost_usd + ($call->costUsd ?? 0.0), 8);
        $this->row->save();
    }
}
