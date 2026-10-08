<?php

namespace Nsd7\AiMeter\Recording\Redaction;

use Nsd7\AiMeter\Core\Contracts\Redactor;

/**
 * Scrubs the most common secrets/PII from stored prompt and response text.
 * It is deliberately conservative; projects can bind their own Redactor.
 */
class DefaultRedactor implements Redactor
{
    /** @var array<int, string> */
    protected array $patterns = [
        '/\b(sk|rk|pk)-[A-Za-z0-9_\-]{16,}\b/',          // OpenAI-style keys
        '/\bsk-ant-[A-Za-z0-9_\-]{16,}\b/',               // Anthropic keys
        '/\bBearer\s+[A-Za-z0-9._\-]{16,}\b/i',           // bearer tokens
        '/\bAKIA[0-9A-Z]{16}\b/',                         // AWS access key ids
        '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/', // emails
        '/\b(?:\d[ \-]*?){13,16}\b/',                     // card-like numbers
    ];

    public function scrub(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        return (string) preg_replace($this->patterns, '[redacted]', $text);
    }
}
