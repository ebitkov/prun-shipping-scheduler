<?php

namespace App\Dto;

final readonly class ShippingTask
{
    /**
     * @param array<string, float> $materials ticker => amount
     */
    public function __construct(
        public ShippingTaskType $type,
        public ShipClass $shipClass,
        public string $planetName,
        public string $planetId,
        public array $materials,
        public \DateTimeImmutable $dueDate,
    ) {
    }
}
