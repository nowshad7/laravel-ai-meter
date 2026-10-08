<?php

namespace Nsd7\AiMeter\Runs\Exceptions;

use RuntimeException;

/**
 * Thrown when a run hits a non-cost limit: too many tool calls or too much
 * wall-clock time. This is the runaway-loop guard.
 */
class RunLimitExceededException extends RuntimeException
{
    public const REASON_TOOL_CALLS = 'tool_calls';
    public const REASON_WALL_CLOCK = 'wall_clock';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "AI run limit exceeded: {$reason}.");
    }

    public static function toolCalls(int $limit): self
    {
        return new self(self::REASON_TOOL_CALLS, "AI run exceeded the tool-call cap of {$limit}.");
    }

    public static function wallClock(int $seconds): self
    {
        return new self(self::REASON_WALL_CLOCK, "AI run exceeded the wall-clock cap of {$seconds}s.");
    }
}
