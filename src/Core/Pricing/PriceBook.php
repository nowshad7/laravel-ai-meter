<?php

namespace Nsd7\AiMeter\Core\Pricing;

use Nsd7\AiMeter\Core\Contracts\PriceProvider;

/**
 * A static, in-memory price table keyed by "provider/model", with a fall back
 * to a model-only lookup and an optional default price for unknown models.
 */
class PriceBook implements PriceProvider
{
    /** @var array<string, ModelPrice> */
    protected array $prices = [];

    /** @var array<string, ModelPrice> */
    protected array $byModel = [];

    public function __construct(?ModelPrice $default = null)
    {
        $this->default = $default;
    }

    protected ?ModelPrice $default;

    /**
     * Build a price book from a nested config array:
     * [ 'openai' => [ 'gpt-4o' => ['input' => 2.5, 'output' => 10], ... ], ... ]
     *
     * @param array<string, array<string, array<string, mixed>>> $data
     */
    public static function fromArray(array $data, ?ModelPrice $default = null): self
    {
        $book = new self($default);

        foreach ($data as $provider => $models) {
            foreach ($models as $model => $price) {
                $book->set((string) $provider, (string) $model, ModelPrice::fromArray($price));
            }
        }

        return $book;
    }

    public function set(string $provider, string $model, ModelPrice $price): void
    {
        $this->prices[$this->key($provider, $model)] = $price;
        $this->byModel[strtolower($model)] = $price;
    }

    public function priceFor(string $provider, string $model): ?ModelPrice
    {
        return $this->prices[$this->key($provider, $model)]
            ?? $this->byModel[strtolower($model)]
            ?? $this->default;
    }

    protected function key(string $provider, string $model): string
    {
        return strtolower($provider) . '/' . strtolower($model);
    }
}
