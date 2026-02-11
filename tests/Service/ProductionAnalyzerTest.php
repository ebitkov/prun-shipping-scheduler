<?php

namespace App\Tests\Service;

use App\Dto\ProductionLine;
use App\Dto\ProductionOrder;
use App\Dto\RecipeIO;
use App\Service\ProductionAnalyzer;
use PHPUnit\Framework\TestCase;

final class ProductionAnalyzerTest extends TestCase
{
    private ProductionAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ProductionAnalyzer();
    }

    public function testRecurringOrdersAreIncluded(): void
    {
        $lines = [
            new ProductionLine('XY-123a', 'TestPlanet', 'factory', [
                new ProductionOrder(
                    inputs: [new RecipeIO('FE', 10.0)],
                    outputs: [new RecipeIO('STL', 5.0)],
                    durationMs: 86_400_000, // exactly 1 day
                    completedPercentage: 0.0,
                    isHalted: false,
                    recurring: true,
                    startedEpochMs: null,
                ),
            ]),
        ];

        $rates = $this->analyzer->getDailyRates($lines);

        self::assertEqualsWithDelta(10.0, $rates['consumption']['FE'], 0.001);
        self::assertEqualsWithDelta(5.0, $rates['production']['STL'], 0.001);
    }

    public function testNonRecurringOrdersAreIgnored(): void
    {
        $lines = [
            new ProductionLine('XY-123a', 'TestPlanet', 'factory', [
                new ProductionOrder(
                    inputs: [new RecipeIO('FE', 10.0)],
                    outputs: [new RecipeIO('STL', 5.0)],
                    durationMs: 86_400_000,
                    completedPercentage: 0.5,
                    isHalted: false,
                    recurring: false,
                    startedEpochMs: 1000,
                ),
            ]),
        ];

        $rates = $this->analyzer->getDailyRates($lines);

        self::assertEmpty($rates['consumption']);
        self::assertEmpty($rates['production']);
    }

    public function testHaltedOrdersAreIgnored(): void
    {
        $lines = [
            new ProductionLine('XY-123a', 'TestPlanet', 'factory', [
                new ProductionOrder(
                    inputs: [new RecipeIO('FE', 10.0)],
                    outputs: [new RecipeIO('STL', 5.0)],
                    durationMs: 86_400_000,
                    completedPercentage: 0.0,
                    isHalted: true,
                    recurring: true,
                    startedEpochMs: null,
                ),
            ]),
        ];

        $rates = $this->analyzer->getDailyRates($lines);

        self::assertEmpty($rates['consumption']);
        self::assertEmpty($rates['production']);
    }

    public function testMultipleOrdersAggregate(): void
    {
        $lines = [
            new ProductionLine('XY-123a', 'TestPlanet', 'factory', [
                new ProductionOrder(
                    inputs: [new RecipeIO('FE', 10.0)],
                    outputs: [new RecipeIO('STL', 5.0)],
                    durationMs: 86_400_000,
                    completedPercentage: 0.0,
                    isHalted: false,
                    recurring: true,
                    startedEpochMs: null,
                ),
                new ProductionOrder(
                    inputs: [new RecipeIO('FE', 20.0)],
                    outputs: [new RecipeIO('STL', 10.0)],
                    durationMs: 86_400_000,
                    completedPercentage: 0.0,
                    isHalted: false,
                    recurring: true,
                    startedEpochMs: null,
                ),
            ]),
        ];

        $rates = $this->analyzer->getDailyRates($lines);

        self::assertEqualsWithDelta(30.0, $rates['consumption']['FE'], 0.001);
        self::assertEqualsWithDelta(15.0, $rates['production']['STL'], 0.001);
    }

    public function testExtractorWithNoInputs(): void
    {
        $lines = [
            new ProductionLine('XY-123a', 'TestPlanet', 'extractor', [
                new ProductionOrder(
                    inputs: [],
                    outputs: [new RecipeIO('ALO', 14.0)],
                    durationMs: 43_200_000, // 12 hours = 2 cycles/day
                    completedPercentage: 0.0,
                    isHalted: false,
                    recurring: true,
                    startedEpochMs: null,
                ),
            ]),
        ];

        $rates = $this->analyzer->getDailyRates($lines);

        self::assertEmpty($rates['consumption']);
        self::assertEqualsWithDelta(28.0, $rates['production']['ALO'], 0.001);
    }

    public function testEmptyProductionLinesReturnEmptyRates(): void
    {
        $rates = $this->analyzer->getDailyRates([]);

        self::assertEmpty($rates['consumption']);
        self::assertEmpty($rates['production']);
    }
}
