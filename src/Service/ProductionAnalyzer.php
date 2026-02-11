<?php

namespace App\Service;

use App\Dto\ProductionLine;

final class ProductionAnalyzer
{
    private const float MS_PER_DAY = 86_400_000.0;

    /**
     * @param ProductionLine[] $productionLines
     * @return array{consumption: array<string, float>, production: array<string, float>}
     */
    public function getDailyRates(array $productionLines): array
    {
        $dailyConsumption = [];
        $dailyProduction = [];

        foreach ($productionLines as $line) {
            foreach ($line->orders as $order) {
                if (!$order->recurring || $order->isHalted) {
                    continue;
                }

                $cyclesPerDay = self::MS_PER_DAY / $order->durationMs;

                foreach ($order->inputs as $input) {
                    $dailyConsumption[$input->ticker] =
                        ($dailyConsumption[$input->ticker] ?? 0.0) + $input->amount * $cyclesPerDay;
                }

                foreach ($order->outputs as $output) {
                    $dailyProduction[$output->ticker] =
                        ($dailyProduction[$output->ticker] ?? 0.0) + $output->amount * $cyclesPerDay;
                }
            }
        }

        return [
            'consumption' => $dailyConsumption,
            'production' => $dailyProduction,
        ];
    }
}
