<?php

namespace App\Service;

use App\Dto\Material;
use App\Dto\ProductionLine;
use App\Dto\Storage;

interface FioApiClientInterface
{
    /**
     * @return Material[]
     */
    public function getAllMaterials(): array;

    /**
     * @return list<string> planet natural IDs
     */
    public function getPlayerPlanets(): array;

    /**
     * @return ProductionLine[]
     */
    public function getProductionLines(string $planet): array;

    public function getStorage(string $planet): Storage;
}
