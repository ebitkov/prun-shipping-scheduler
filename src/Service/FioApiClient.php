<?php

namespace App\Service;

use App\Dto\Material;
use App\Dto\ProductionLine;
use App\Dto\ProductionOrder;
use App\Dto\RecipeIO;
use App\Dto\Storage;
use App\Dto\StorageItem;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @phpstan-type MaterialData array{Ticker: string, Name: string, Weight: float, Volume: float}
 * @phpstan-type RecipeIOData array{MaterialTicker: string, MaterialAmount: float}
 * @phpstan-type OrderData array{
 *     Inputs: list<RecipeIOData>,
 *     Outputs: list<RecipeIOData>,
 *     DurationMs: int,
 *     CompletedPercentage: float|null,
 *     IsHalted: bool,
 *     Recurring: bool,
 *     StartedEpochMs: int|null
 * }
 * @phpstan-type ProductionLineData array{
 *     PlanetNaturalId: string,
 *     PlanetName: string,
 *     Type: string,
 *     Orders: list<OrderData>
 * }
 * @phpstan-type StorageItemData array{MaterialTicker: string, MaterialAmount: float}
 * @phpstan-type StorageData array{StorageItems: list<StorageItemData>}
 */
final class FioApiClient implements FioApiClientInterface
{
    private HttpClientInterface $httpClient;

    public function __construct(
        private readonly CacheInterface $fioStaticData,
        private readonly CacheInterface $fioDynamicData,
        private readonly string $fioBaseUrl,
        private readonly string $fioApiKey,
        private readonly string $fioUsername,
    ) {
        $this->httpClient = HttpClient::createForBaseUri($this->fioBaseUrl);
    }

    /**
     * @return Material[]
     * @throws InvalidArgumentException
     */
    public function getAllMaterials(): array
    {
        return $this->fioStaticData->get('fio_all_materials', function (ItemInterface $item): array {
            /** @var list<MaterialData> $data */
            $data = $this->request('GET', '/material/allmaterials');

            return array_map(
                static fn(array $m) => new Material(
                    ticker: $m['Ticker'],
                    name: $m['Name'],
                    weight: $m['Weight'],
                    volume: $m['Volume'],
                ),
                $data,
            );
        });
    }

    /**
     * @return list<string> planet natural IDs
     * @throws InvalidArgumentException
     */
    public function getPlayerPlanets(): array
    {
        return $this->fioDynamicData->get('fio_player_planets', function (ItemInterface $item): array {
            /** @var list<string> $data */
            $data = $this->request('GET', "/production/planets/{$this->fioUsername}", auth: true);

            return $data;
        });
    }

    /**
     * @return ProductionLine[]
     * @throws InvalidArgumentException
     */
    public function getProductionLines(string $planet): array
    {
        $cacheKey = 'fio_production_' . str_replace(['{', '}', '(', ')', '/', '\\', '@', ':'], '_', $planet);

        return $this->fioDynamicData->get($cacheKey, function (ItemInterface $item) use ($planet): array {
            $response = $this->requestRaw(
                'GET',
                "/production/{$this->fioUsername}/{$planet}",
                auth: true,
            );

            if ($response->getStatusCode() >= 300 || '' === $response->getContent(false)) {
                return [];
            }

            /** @var list<ProductionLineData> $data */
            $data = $response->toArray();

            return array_map(fn(array $line) => $this->mapProductionLine($line), $data);
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getStorage(string $planet): Storage
    {
        $cacheKey = 'fio_storage_' . str_replace(['{', '}', '(', ')', '/', '\\', '@', ':'], '_', $planet);

        return $this->fioDynamicData->get($cacheKey, function (ItemInterface $item) use ($planet): Storage {
            $response = $this->requestRaw(
                'GET',
                "/storage/{$this->fioUsername}/{$planet}",
                auth: true,
            );

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 300 || '' === $response->getContent(false)) {
                return new Storage([]);
            }

            /** @var StorageData $data */
            $data = $response->toArray();

            /** @var array<string, float> $items */
            $items = [];
            foreach ($data['StorageItems'] as $entry) {
                $ticker = $entry['MaterialTicker'];
                $amount = $entry['MaterialAmount'];
                if (isset($items[$ticker])) {
                    $items[$ticker] += $amount;
                } else {
                    $items[$ticker] = $amount;
                }
            }

            return new Storage(
                items: array_map(
                    static fn(string $ticker, float $amount) => new StorageItem($ticker, $amount),
                    array_keys($items),
                    array_values($items),
                ),
            );
        });
    }

    /**
     * @param ProductionLineData $line
     */
    private function mapProductionLine(array $line): ProductionLine
    {
        return new ProductionLine(
            planetNaturalId: $line['PlanetNaturalId'],
            planetName: $line['PlanetName'],
            type: $line['Type'],
            orders: array_map(fn(array $o) => $this->mapOrder($o), $line['Orders']),
        );
    }

    /**
     * @param OrderData $o
     */
    private function mapOrder(array $o): ProductionOrder
    {
        return new ProductionOrder(
            inputs: array_map(
                static fn(array $i) => new RecipeIO($i['MaterialTicker'], $i['MaterialAmount']),
                $o['Inputs'],
            ),
            outputs: array_map(
                static fn(array $i) => new RecipeIO($i['MaterialTicker'], $i['MaterialAmount']),
                $o['Outputs'],
            ),
            durationMs: $o['DurationMs'],
            completedPercentage: $o['CompletedPercentage'] ?? 0.0,
            isHalted: $o['IsHalted'],
            recurring: $o['Recurring'],
            startedEpochMs: $o['StartedEpochMs'],
        );
    }

    /**
     * @return array<mixed>
     *
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    private function request(string $method, string $url, bool $auth = false): array
    {
        return $this->requestRaw($method, $url, $auth)->toArray();
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function requestRaw(
        string $method,
        string $url,
        bool $auth = false,
    ): ResponseInterface {
        $options = [];
        if ($auth) {
            $options['headers'] = [
                'Authorization' => $this->fioApiKey,
            ];
        }

        return $this->httpClient->request($method, $url, $options);
    }
}
