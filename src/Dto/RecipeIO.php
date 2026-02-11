<?php

namespace App\Dto;

final readonly class RecipeIO
{
    public function __construct(
        public string $ticker,
        public float $amount,
    ) {
    }
}
