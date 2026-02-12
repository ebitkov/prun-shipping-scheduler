<?php

namespace App\Command;

use App\Service\FioApiClientInterface;
use App\Service\MaterialRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fio:inventory',
    description: 'Display inventory for a planet',
)]
final class FioInventoryCommand extends Command
{
    public function __construct(
        private readonly FioApiClientInterface $fioApiClient,
        private readonly MaterialRegistry $materialRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('planet', null, InputOption::VALUE_REQUIRED, 'Planet name or natural ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $planet */
        $planet = $input->getOption('planet');

        if ($planet === null) {
            $output->writeln('<error>The --planet option is required.</error>');

            return Command::FAILURE;
        }

        $storage = $this->fioApiClient->getStorage($planet);

        if ($storage->items === []) {
            $output->writeln('<comment>No items found in storage on ' . $planet . '.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaderTitle('Inventory — ' . $planet);
        $table->setHeaders(['Ticker', 'Name', 'Amount', 'Total Volume (m³)', 'Total Mass (kg)']);

        $totalVolume = 0.0;
        $totalMass = 0.0;

        foreach ($storage->items as $item) {
            $material = $this->materialRegistry->get($item->materialTicker);
            $itemVolume = $item->materialAmount * $material->volume;
            $itemMass = $item->materialAmount * $material->weight;
            $totalVolume += $itemVolume;
            $totalMass += $itemMass;

            $table->addRow([
                $material->ticker,
                $material->name,
                number_format($item->materialAmount, 0, ',', '.'),
                number_format($itemVolume, 2, ',', '.'),
                number_format($itemMass, 2, ',', '.'),
            ]);
        }

        $table->addRow(['', '', '', '', '']);
        $table->addRow([
            '<info>Total</info>',
            '',
            '',
            '<info>' . number_format($totalVolume, 2, ',', '.') . '</info>',
            '<info>' . number_format($totalMass, 2, ',', '.') . '</info>',
        ]);

        $table->render();

        return Command::SUCCESS;
    }
}
