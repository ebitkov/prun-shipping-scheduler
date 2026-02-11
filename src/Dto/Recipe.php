<?php

namespace App\Dto;

final readonly class Recipe
{
    /**
     * @param RecipeIO[] $inputs
     * @param RecipeIO[] $outputs
     */
    public function __construct(
        public string $buildingTicker,
        public string $recipeName,
        public array $inputs,
        public array $outputs,
        public int $timeMs,
    ) {
    }
}
