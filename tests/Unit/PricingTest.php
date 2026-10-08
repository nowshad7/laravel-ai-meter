<?php

namespace Nsd7\AiMeter\Tests\Unit;

use Nsd7\AiMeter\Core\Data\TokenUsage;
use Nsd7\AiMeter\Core\Pricing\ModelPrice;
use Nsd7\AiMeter\Core\Pricing\PriceBook;
use PHPUnit\Framework\TestCase;

class PricingTest extends TestCase
{
    public function test_model_price_computes_input_and_output_cost()
    {
        $price = new ModelPrice(inputPerMillion: 2.5, outputPerMillion: 10.0);
        $usage = TokenUsage::of(1_000_000, 500_000);

        // 1M input * 2.5 + 0.5M output * 10 = 2.5 + 5 = 7.5
        $this->assertSame(7.5, $price->cost($usage));
    }

    public function test_cached_tokens_use_the_cached_rate_and_are_subtracted_from_prompt()
    {
        $price = new ModelPrice(inputPerMillion: 2.5, outputPerMillion: 10.0, cachedInputPerMillion: 0.25);
        $usage = new TokenUsage(promptTokens: 1_000_000, completionTokens: 0, totalTokens: 1_000_000, cachedTokens: 400_000);

        // billable prompt 600k * 2.5 + cached 400k * 0.25 = 1.5 + 0.1 = 1.6
        $this->assertSame(1.6, $price->cost($usage));
    }

    public function test_price_book_resolves_exact_then_model_then_default()
    {
        $book = PriceBook::fromArray([
            'openai' => ['gpt-4o' => ['input' => 2.5, 'output' => 10]],
        ], default: new ModelPrice(1, 3));

        $this->assertNotNull($book->priceFor('openai', 'gpt-4o'));
        $this->assertSame(2.5, $book->priceFor('openai', 'gpt-4o')->inputPerMillion);

        // model-only match ignores provider
        $this->assertSame(2.5, $book->priceFor('azure', 'gpt-4o')->inputPerMillion);

        // unknown model falls back to the default
        $this->assertSame(1.0, $book->priceFor('openai', 'mystery')->inputPerMillion);
    }

    public function test_price_book_returns_null_without_a_default()
    {
        $book = PriceBook::fromArray([]);

        $this->assertNull($book->priceFor('openai', 'nope'));
    }
}
