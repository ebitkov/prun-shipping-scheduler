<?php

namespace App\Service;

use App\Dto\Material;

final class MaterialRegistry
{
    /** @var array<string, Material>|null */
    private ?array $materials = null;

    public function __construct(
        private readonly FioApiClientInterface $fioApiClient,
    ) {
    }

    public function get(string $ticker): Material
    {
        return $this->getAll()[$ticker] ?? throw new \RuntimeException("Unknown material ticker: {$ticker}");
    }

    /**
     * @return array<string, Material>
     */
    public function getAll(): array
    {
        if ($this->materials === null) {
            $this->materials = [];
            foreach ($this->fioApiClient->getAllMaterials() as $material) {
                $this->materials[$material->ticker] = $material;
            }
        }

        return $this->materials;
    }
}
