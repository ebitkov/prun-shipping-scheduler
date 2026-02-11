<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PriceService
{
    private HttpClientInterface $httpClient;

    public function __construct(
        private readonly CacheInterface $fioDynamicDataCache,
        private readonly string $fioBaseUrl,
    ) {
        $this->httpClient = HttpClient::createForBaseUri($this->fioBaseUrl);
    }

    /**
     * @return array<string, array{ask: float, bid: float}>
     */
    public function getPrices(): array
    {
        return $this->fioDynamicDataCache->get('fnar_cx_prices', function (ItemInterface $item): array {
            $response = $this->httpClient->request('GET', '/csv/prices');

            if ($response->getStatusCode() >= 300) {
                return [];
            }

            return $this->parseCsv($response->getContent());
        });
    }

    /**
     * @return array<string, array{ask: float, bid: float}>
     */
    private function parseCsv(string $csv): array
    {
        $lines = explode("\n", $csv);
        if (\count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv($lines[0]);
        $tickerIdx = array_search('Ticker', $headers, true);
        $askIdx = array_search('AI1-AskPrice', $headers, true);
        $bidIdx = array_search('AI1-BidPrice', $headers, true);

        if ($tickerIdx === false || $askIdx === false || $bidIdx === false) {
            return [];
        }

        $maxIdx = max($tickerIdx, $askIdx, $bidIdx);
        $prices = [];

        for ($i = 1, $count = \count($lines); $i < $count; ++$i) {
            $row = str_getcsv($lines[$i]);
            $ticker = $row[$tickerIdx] ?? '';
            if (\count($row) <= $maxIdx || $ticker === '') {
                continue;
            }

            $prices[$ticker] = [
                'ask' => (float) $row[$askIdx],
                'bid' => (float) $row[$bidIdx],
            ];
        }

        return $prices;
    }
}
