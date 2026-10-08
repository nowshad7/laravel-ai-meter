<?php

namespace Nsd7\AiMeter\Core\Contracts;

use Nsd7\AiMeter\Core\Data\LlmCall;

interface Recorder
{
    /**
     * Persist a normalized AI call.
     */
    public function record(LlmCall $call): void;
}
