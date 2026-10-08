<?php

namespace Nsd7\AiMeter\Core\Contracts;

use Nsd7\AiMeter\Core\Pricing\ModelPrice;

interface PriceProvider
{
    /**
     * Resolve the price for a provider/model pair, or null when unknown.
     */
    public function priceFor(string $provider, string $model): ?ModelPrice;
}
