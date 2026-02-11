<?php

namespace App\Dto;

final readonly class ProductionOrder
{
    /**
     * @param RecipeIO[] $inputs
     * @param RecipeIO[] $outputs
     */
    public function __construct(
        public array $inputs,
        public array $outputs,
        public int $durationMs,
        public float $completedPercentage,
        public bool $isHalted,
        public bool $recurring,
        public ?int $startedEpochMs,
    ) {
    }
}
