<?php

namespace Nsd7\AiMeter\Core\Contracts;

interface Redactor
{
    /**
     * Scrub secrets/PII from text before it is stored, or return null as-is.
     */
    public function scrub(?string $text): ?string;
}
