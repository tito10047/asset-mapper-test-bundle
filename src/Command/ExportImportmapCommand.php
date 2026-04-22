<?php

namespace Tito10047\AssetMapperTestBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\AssetMapperTestBundle\Setup\ImportmapJsonExporter;

#[AsCommand(
    name: 'asset-mapper-test:export',
    description: 'Export importmap.php to JSON for the Node.js loader (symlink-free variant)',
)]
class ExportImportmapCommand extends Command
{
    public function __construct(
        private readonly ImportmapJsonExporter $exporter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        try {
            $result = $this->exporter->export();
        } catch (\RuntimeException $e) {
            $errorOutput->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $io->writeln(sprintf(
            'Exported %d entries (skipped %d) to %s',
            $result->exported,
            $result->skipped,
            $result->outputPath,
        ));

        return Command::SUCCESS;
    }
}
