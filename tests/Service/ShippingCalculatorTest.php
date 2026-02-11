<?php

namespace App\Tests\Service;

use App\Dto\Material;
use App\Dto\ProductionLine;
use App\Dto\ProductionOrder;
use App\Dto\RecipeIO;
use App\Dto\ShipClass;
use App\Dto\ShippingTaskType;
use App\Dto\Storage;
use App\Dto\StorageItem;
use App\Service\FioApiClientInterface;
use App\Service\MaterialRegistry;
use App\Service\ProductionAnalyzer;
use App\Service\ShippingCalculator;
use PHPUnit\Framework\TestCase;

final class ShippingCalculatorTest extends TestCase
{
    /**
     * Validates the AL example from PROJECT.md:
     * AL (Weight=2.7t, Volume=1.0m3)
     * - LCB: min(2000/2.7, 2000/1.0) = 740
     * - WCB: min(3000/2.7, 1000/1.0) = 1000
     * - VCB: min(1000/2.7, 3000/1.0) = 370
     * -> WCB wins with 1000 AL
     */
    public function testAluminiumExportSelectsWcbWith1000Units(): void
    {
        // Planet extracts AL only (no inputs) -> pure export
        $productionLines = [
            new ProductionLine('ZV-759c', 'Deimos', 'smelter', [
                new ProductionOrder(
                    inputs: [],
                    outputs: [new RecipeIO('AL', 100.0)],
                    durationMs: 86_400_000, // 1 day -> 100 AL/day
                    completedPercentage: 0.0,
                    isHalted: false,
                    recurring: true,
                    startedEpochMs: null,
                ),
            ]),
        ];

        // Empty stock -> export will take days to fill
        $storage = new Storage([]);

        $calculator = $this->buildCalculator(
            planets: ['ZV-759c'],
            productionLines: ['ZV-759c' => $productionLines],
            storages: ['ZV-759c' => $storage],
            materials: [
                new Material('AL', 'Aluminium', 2.7, 1.0),
            ],
        );

        $tasks = $calculator->calculateAllTasks();

        self::assertCount(1, $tasks);
        $task = $tasks[0];

        self::assertSame(ShippingTaskType::Export, $task->type);
        self::assertSame(ShipClass::WCB, $task->shipClass);
        self::assertSame('Deimos', $task->planetName);
        self::assertSame(1000, $task->materials['AL']);
    }

    public function testImportTaskWhenNetConsumption(): void
    {
        // Consumes 20 FE/day, produces 10 STL/day
        $productionLines = [
            new ProductionLine('XY-123a', 'Vulcan', 'factory', [
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

        // FE: Weight=7.8, Volume=1.0
        // STL: Weight=7.8, Volume=1.0
        // Import (FE only): daily 20 FE
        //   dayWeight = 20 * 7.8 = 156, dayVolume = 20 * 1.0 = 20
        //   LCB: min(2000/156, 2000/20) = min(12.82, 100) = 12.82 days
        //   WCB: min(3000/156, 1000/20) = min(19.23, 50)  = 19.23 days
        //   VCB: min(1000/156, 3000/20) = min(6.41, 150)  = 6.41 days
        //   -> WCB wins, shipload = floor(20 * 19.23) = 384 FE

        $storage = new Storage([
            new StorageItem('FE', 500.0),
            new StorageItem('STL', 50.0),
        ]);

        $calculator = $this->buildCalculator(
            planets: ['XY-123a'],
            productionLines: ['XY-123a' => $productionLines],
            storages: ['XY-123a' => $storage],
            materials: [
                new Material('FE', 'Iron', 7.8, 1.0),
                new Material('STL', 'Steel', 7.8, 1.0),
            ],
        );

        $tasks = $calculator->calculateAllTasks();

        // Should have import (FE) and export (STL)
        $importTasks = array_values(array_filter(
            $tasks,
            fn($t) => $t->type === ShippingTaskType::Import
        ));
        $exportTasks = array_values(array_filter(
            $tasks,
            fn($t) => $t->type === ShippingTaskType::Export
        ));

        self::assertCount(1, $importTasks);
        self::assertCount(1, $exportTasks);

        $import = $importTasks[0];
        self::assertSame(ShipClass::WCB, $import->shipClass);
        self::assertSame(384, $import->materials['FE']);
        self::assertArrayNotHasKey('STL', $import->materials);

        $export = $exportTasks[0];
        self::assertArrayHasKey('STL', $export->materials);
        self::assertArrayNotHasKey('FE', $export->materials);
    }

    public function testImportBeforeExportOnSameDay(): void
    {
        // Both import and export due today (stock is 0)
        $productionLines = [
            new ProductionLine('XY-123a', 'Vulcan', 'factory', [
                new ProductionOrder(
                    inputs: [new RecipeIO('FE', 10.0)],
                    outputs: [new RecipeIO('STL', 5.0)],
                    durationMs: 86_400_000,
                    completedPercentage: 0.0,
                    isHalted: false,
                    recurring: true,
                    startedEpochMs: null,
                ),
            ]),
        ];

        $storage = new Storage([
            new StorageItem('STL', 9999.0), // enough for export to be due today
        ]);

        $calculator = $this->buildCalculator(
            planets: ['XY-123a'],
            productionLines: ['XY-123a' => $productionLines],
            storages: ['XY-123a' => $storage],
            materials: [
                new Material('FE', 'Iron', 7.8, 1.0),
                new Material('STL', 'Steel', 7.8, 1.0),
            ],
        );

        $tasks = $calculator->calculateAllTasks();

        self::assertGreaterThanOrEqual(2, count($tasks));
        // Find the first import and first export
        $firstImportIdx = null;
        $firstExportIdx = null;
        foreach ($tasks as $idx => $task) {
            if ($task->type === ShippingTaskType::Import && $firstImportIdx === null) {
                $firstImportIdx = $idx;
            }
            if ($task->type === ShippingTaskType::Export && $firstExportIdx === null) {
                $firstExportIdx = $idx;
            }
        }
        self::assertNotNull($firstImportIdx);
        self::assertNotNull($firstExportIdx);
        self::assertLessThan($firstExportIdx, $firstImportIdx);
    }

    public function testNoPlanetsMeansNoTasks(): void
    {
        $calculator = $this->buildCalculator(
            planets: [],
            productionLines: [],
            storages: [],
            materials: [],
        );

        self::assertSame([], $calculator->calculateAllTasks());
    }

    public function testNoRecurringOrdersMeansNoTasks(): void
    {
        $productionLines = [
            new ProductionLine('XY-123a', 'Vulcan', 'factory', [
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

        $calculator = $this->buildCalculator(
            planets: ['XY-123a'],
            productionLines: ['XY-123a' => $productionLines],
            storages: ['XY-123a' => new Storage([])],
            materials: [
                new Material('FE', 'Iron', 7.8, 1.0),
                new Material('STL', 'Steel', 7.8, 1.0),
            ],
        );

        self::assertSame([], $calculator->calculateAllTasks());
    }

    public function testDueDateIsInFutureWithLargeStock(): void
    {
        // 10 FE consumed/day, 1000 FE in stock
        $productionLines = [
            new ProductionLine('XY-123a', 'Vulcan', 'factory', [
                new ProductionOrder(
                    inputs: [new RecipeIO('FE', 10.0)],
                    outputs: [new RecipeIO('STL', 5.0)],
                    durationMs: 86_400_000,
                    completedPercentage: 0.0,
                    isHalted: false,
                    recurring: true,
                    startedEpochMs: null,
                ),
            ]),
        ];

        // FE: dayWeight=10*7.8=78, dayVolume=10*1.0=10
        // WCB: min(3000/78, 1000/10) = min(38.46, 100) = 38.46
        // shipload FE = floor(10 * 38.46) = 384
        // surplus = 1000 - 384 = 616
        // days = 616 / 10 = 61.6 -> 61 days in future

        $storage = new Storage([
            new StorageItem('FE', 1000.0),
        ]);

        $calculator = $this->buildCalculator(
            planets: ['XY-123a'],
            productionLines: ['XY-123a' => $productionLines],
            storages: ['XY-123a' => $storage],
            materials: [
                new Material('FE', 'Iron', 7.8, 1.0),
                new Material('STL', 'Steel', 7.8, 1.0),
            ],
        );

        $tasks = $calculator->calculateAllTasks();
        $importTasks = array_values(array_filter(
            $tasks,
            fn($t) => $t->type === ShippingTaskType::Import
        ));

        self::assertCount(1, $importTasks);
        $today = new \DateTimeImmutable('today');
        $diff = (int) $today->diff($importTasks[0]->dueDate)->days;
        self::assertSame(61, $diff);
    }

    /**
     * @param string[] $planets
     * @param array<string, ProductionLine[]> $productionLines
     * @param array<string, Storage> $storages
     * @param Material[] $materials
     */
    private function buildCalculator(
        array $planets,
        array $productionLines,
        array $storages,
        array $materials,
    ): ShippingCalculator {
        $client = $this->createStub(FioApiClientInterface::class);
        $client->method('getPlayerPlanets')->willReturn($planets);
        $client->method('getProductionLines')->willReturnCallback(
            fn(string $planet) => $productionLines[$planet] ?? []
        );
        $client->method('getStorage')->willReturnCallback(
            fn(string $planet) => $storages[$planet] ?? new Storage([])
        );
        $client->method('getAllMaterials')->willReturn($materials);

        $registry = new MaterialRegistry($client);
        $analyzer = new ProductionAnalyzer();

        return new ShippingCalculator($client, $registry, $analyzer);
    }
}
