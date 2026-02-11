<?php

namespace App\Tests\Service;

use App\Dto\Material;
use App\Service\FioApiClientInterface;
use App\Service\MaterialRegistry;
use PHPUnit\Framework\TestCase;

final class MaterialRegistryTest extends TestCase
{
    public function testGetReturnsMaterial(): void
    {
        $materials = [
            new Material('AL', 'Aluminium', 2.7, 1.0),
            new Material('FE', 'Iron', 7.8, 1.0),
        ];

        $client = $this->createStub(FioApiClientInterface::class);
        $client->method('getAllMaterials')->willReturn($materials);

        $registry = new MaterialRegistry($client);

        $al = $registry->get('AL');
        self::assertSame('AL', $al->ticker);
        self::assertSame(2.7, $al->weight);

        $fe = $registry->get('FE');
        self::assertSame('FE', $fe->ticker);
    }

    public function testGetThrowsForUnknownTicker(): void
    {
        $client = $this->createStub(FioApiClientInterface::class);
        $client->method('getAllMaterials')->willReturn([]);

        $registry = new MaterialRegistry($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown material ticker: ZZZ');
        $registry->get('ZZZ');
    }

    public function testLazyLoadingOnlyCallsApiOnce(): void
    {
        $client = $this->createMock(FioApiClientInterface::class);
        $client->expects(self::once())
            ->method('getAllMaterials')
            ->willReturn([new Material('AL', 'Aluminium', 2.7, 1.0)]);

        $registry = new MaterialRegistry($client);

        $registry->get('AL');
        $registry->get('AL');
        $registry->getAll();
    }
}
