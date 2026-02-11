<?php

namespace App\Dto;

final readonly class StorageItem
{
    public function __construct(
        public string $materialTicker,
        public float $materialAmount,
    ) {
    }
}
