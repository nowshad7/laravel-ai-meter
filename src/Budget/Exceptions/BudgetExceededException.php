<?php

namespace Nsd7\AiMeter\Budget\Exceptions;

use Nsd7\AiMeter\Core\Budget\BudgetDecision;
use RuntimeException;

/**
 * Thrown programmatically (e.g. inside a run) when a blocking budget is
 * exceeded. The HTTP middleware uses abort(402) instead.
 */
class BudgetExceededException extends RuntimeException
{
    public function __construct(
        public readonly ?BudgetDecision $decision = null,
        string $message = 'AI budget exceeded.',
    ) {
        parent::__construct($message);
    }
}
