<?php

namespace App\Service;

use App\Dto\ShipClass;
use App\Dto\ShippingTask;
use App\Dto\ShippingTaskType;

final class ShippingCalculator
{
    public function __construct(
        private readonly FioApiClientInterface $fioApiClient,
        private readonly MaterialRegistry $materialRegistry,
        private readonly ProductionAnalyzer $productionAnalyzer,
    ) {
    }

    /**
     * @return ShippingTask[]
     */
    public function calculateAllTasks(): array
    {
        $planets = $this->fioApiClient->getPlayerPlanets();
        $tasks = [];

        foreach ($planets as $planetId) {
            $tasks = array_merge($tasks, $this->calculateForPlanet($planetId));
        }

        // Sort: date ascending, then import before export
        usort($tasks, function (ShippingTask $a, ShippingTask $b): int {
            $dateCmp = $a->dueDate <=> $b->dueDate;
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            // Import before Export
            return ($a->type === ShippingTaskType::Import ? 0 : 1) - ($b->type === ShippingTaskType::Import ? 0 : 1);
        });

        return $tasks;
    }

    /**
     * @return ShippingTask[]
     */
    public function calculateForPlanet(string $planetId): array
    {
        $productionLines = $this->fioApiClient->getProductionLines($planetId);

        if (empty($productionLines)) {
            return [];
        }

        $planetName = $productionLines[0]->planetName;
        $rates = $this->productionAnalyzer->getDailyRates($productionLines);
        $dailyConsumption = $rates['consumption'];
        $dailyProduction = $rates['production'];

        // Step 1: Net flows
        $allTickers = array_unique(array_merge(array_keys($dailyConsumption), array_keys($dailyProduction)));
        $netConsumption = []; // needs import
        $netProduction = [];  // needs export

        foreach ($allTickers as $ticker) {
            $consumed = $dailyConsumption[$ticker] ?? 0.0;
            $produced = $dailyProduction[$ticker] ?? 0.0;
            $net = $produced - $consumed;

            if ($net < 0) {
                $netConsumption[$ticker] = abs($net);
            } elseif ($net > 0) {
                $netProduction[$ticker] = $net;
            }
        }

        // Get current stock
        $storage = $this->fioApiClient->getStorage($planetId);
        $currentStock = [];
        foreach ($storage->items as $item) {
            $currentStock[$item->materialTicker] = $item->materialAmount;
        }

        $tasks = [];
        $today = new \DateTimeImmutable('today');

        // Import task
        if (!empty($netConsumption)) {
            $task = $this->buildTask(
                ShippingTaskType::Import,
                $netConsumption,
                $currentStock,
                $planetName,
                $planetId,
                $today,
            );
            if ($task !== null) {
                $tasks[] = $task;
            }
        }

        // Export task
        if (!empty($netProduction)) {
            $task = $this->buildTask(
                ShippingTaskType::Export,
                $netProduction,
                $currentStock,
                $planetName,
                $planetId,
                $today,
            );
            if ($task !== null) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    /**
     * @param array<string, float> $dailyRates
     * @param array<string, float> $currentStock
     */
    private function buildTask(
        ShippingTaskType $type,
        array $dailyRates,
        array $currentStock,
        string $planetName,
        string $planetId,
        \DateTimeImmutable $today,
    ): ?ShippingTask {
        // Step 2: Choose optimal ship class
        $bestShip = null;
        $bestDaysFit = 0.0;

        foreach (ShipClass::cases() as $shipClass) {
            $dayWeight = 0.0;
            $dayVolume = 0.0;

            foreach ($dailyRates as $ticker => $rate) {
                $material = $this->materialRegistry->get($ticker);
                $dayWeight += $rate * $material->weight;
                $dayVolume += $rate * $material->volume;
            }

            if ($dayWeight <= 0 || $dayVolume <= 0) {
                continue;
            }

            $daysFit = min(
                $shipClass->weightCapacity() / $dayWeight,
                $shipClass->volumeCapacity() / $dayVolume,
            );

            if ($daysFit > $bestDaysFit) {
                $bestDaysFit = $daysFit;
                $bestShip = $shipClass;
            }
        }

        if ($bestShip === null) {
            return null;
        }

        // Step 3: Calculate shipload
        $shipload = [];
        foreach ($dailyRates as $ticker => $rate) {
            $shipload[$ticker] = (int) floor($rate * $bestDaysFit);
        }

        // Remove zero entries
        $shipload = array_filter($shipload, fn(int $amount) => $amount > 0);
        if (empty($shipload)) {
            return null;
        }

        // Step 4/5: Calculate days until due
        if ($type === ShippingTaskType::Import) {
            // Import: when will stock drop below one shipload?
            $daysUntilDue = PHP_FLOAT_MAX;
            foreach ($dailyRates as $ticker => $rate) {
                $stock = $currentStock[$ticker] ?? 0.0;
                $surplus = $stock - ($shipload[$ticker] ?? 0);
                if ($surplus <= 0) {
                    $daysUntilDue = 0;
                    break;
                }
                $days = $surplus / $rate;
                $daysUntilDue = min($daysUntilDue, $days);
            }
        } else {
            // Export: when will produced stock fill one shipload?
            $daysUntilDue = 0.0;
            foreach ($dailyRates as $ticker => $rate) {
                $stock = $currentStock[$ticker] ?? 0.0;
                $remaining = ($shipload[$ticker] ?? 0) - $stock;
                if ($remaining <= 0) {
                    // Already have enough of this material
                    continue;
                }
                $days = $remaining / $rate;
                $daysUntilDue = max($daysUntilDue, $days);
            }
        }

        $daysInt = max(0, (int) ($type === ShippingTaskType::Export ? ceil($daysUntilDue) : floor($daysUntilDue)));
        $dueDate = $today->modify("+{$daysInt} days");

        return new ShippingTask(
            type: $type,
            shipClass: $bestShip,
            planetName: $planetName,
            planetId: $planetId,
            materials: $shipload,
            dueDate: $dueDate,
        );
    }
}
