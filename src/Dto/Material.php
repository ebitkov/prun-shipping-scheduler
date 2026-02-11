<?php

namespace App\Dto;

final readonly class Material
{
    public function __construct(
        public string $ticker,
        public string $name,
        public float $weight,
        public float $volume,
    ) {
    }
}
