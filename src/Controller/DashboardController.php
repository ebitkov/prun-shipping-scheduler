<?php

namespace App\Controller;

use App\Dto\ShippingTask;
use App\Dto\ShippingTaskType;
use App\Service\PriceService;
use App\Service\ShippingCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(ShippingCalculator $shippingCalculator, PriceService $priceService): Response
    {
        $tasks = $shippingCalculator->calculateAllTasks();
        $grouped = $this->groupByDateLabel($tasks);

        return $this->render('dashboard/index.html.twig', [
            'grouped_tasks' => $grouped,
            'prices' => $priceService->getPrices(),
        ]);
    }

    /**
     * @param ShippingTask[] $tasks
     * @return array<string, ShippingTask[]>
     */
    private function groupByDateLabel(array $tasks): array
    {
        $today = new \DateTimeImmutable('today');
        $grouped = [];

        foreach ($tasks as $task) {
            $label = $this->getDateLabel($task->dueDate, $today);
            $grouped[$label][] = $task;
        }

        return $grouped;
    }

    private function getDateLabel(\DateTimeImmutable $date, \DateTimeImmutable $today): string
    {
        $diff = (int) $today->diff($date)->days;
        if ($date < $today) {
            $diff = 0;
        }

        return match (true) {
            $diff === 0 => 'Heute',
            $diff === 1 => 'Morgen',
            $diff <= 6 => $this->germanWeekday($date),
            default => $date->format('d.m.'),
        };
    }

    private function germanWeekday(\DateTimeImmutable $date): string
    {
        return match ((int) $date->format('N')) {
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            7 => 'Sonntag',
        };
    }
}
