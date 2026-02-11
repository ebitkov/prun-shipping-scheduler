<?php

namespace App\Dto;

final readonly class ProductionLine
{
    /**
     * @param ProductionOrder[] $orders
     */
    public function __construct(
        public string $planetNaturalId,
        public string $planetName,
        public string $type,
        public array $orders,
    ) {
    }
}
