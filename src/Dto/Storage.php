<?php

namespace App\Dto;

final readonly class Storage
{
    /**
     * @param StorageItem[] $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
