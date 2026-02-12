<?php

namespace App\Controller;

use App\Dto\ShippingTask;
use App\Service\FioApiClientInterface;
use App\Service\PriceService;
use App\Service\ShippingCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ApiController extends AbstractController
{
    #[Route('/api/dashboard/stream', name: 'api_dashboard_stream')]
    public function dashboardStream(
        FioApiClientInterface $fioApiClient,
        ShippingCalculator $shippingCalculator,
        PriceService $priceService,
    ): StreamedResponse {
        $response = new StreamedResponse(function () use ($fioApiClient, $shippingCalculator, $priceService): void {
            $send = function (string $event, mixed $data): void {
                echo "event: {$event}\ndata: " . json_encode($data, \JSON_THROW_ON_ERROR) . "\n\n";
                if (\ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                $send('progress', 'Lade Planeten...');

                $planets = $fioApiClient->getPlayerPlanets();

                $send('progress', \count($planets) . ' Planeten gefunden');

                foreach ($planets as $planetId) {
                    $send('progress', "Analysiere {$planetId}...");

                    $tasks = $shippingCalculator->calculateForPlanet($planetId);

                    if (!empty($tasks)) {
                        $send('tasks', array_map($this->serializeTask(...), $tasks));
                    }
                }

                $send('progress', 'Lade Marktpreise...');

                $prices = $priceService->getPrices();
                $send('prices', $prices);

                $send('done', null);
            } catch (\Throwable $e) {
                $send('error', $e->getMessage());
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * @return array{type: string, shipClass: string, planetName: string, planetId: string, materials: array<string, float>, dueDate: string}
     */
    private function serializeTask(ShippingTask $task): array
    {
        return [
            'type' => $task->type->value,
            'shipClass' => $task->shipClass->value,
            'planetName' => $task->planetName,
            'planetId' => $task->planetId,
            'materials' => $task->materials,
            'dueDate' => $task->dueDate->format('Y-m-d'),
        ];
    }
}
