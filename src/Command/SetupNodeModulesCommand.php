<?php

namespace Tito10047\AssetMapperTestBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\AssetMapperTestBundle\Setup\NodeModulesSetup;

#[AsCommand(
    name: 'asset-mapper-test:setup',
    description: 'Setup node_modules symlinks from importmap for JS testing',
)]
class SetupNodeModulesCommand extends Command
{
    public function __construct(
        private readonly NodeModulesSetup $setup,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        try {
            $result = $this->setup->run();
        } catch (\RuntimeException $e) {
            $errorOutput->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $io->writeln(sprintf(
            'Done. Created: %d, Skipped: %d',
            $result->created,
            $result->skipped,
        ));

        if ($result->hasErrors()) {
            foreach ($result->errors as $error) {
                $errorOutput->writeln('<error>' . $error . '</error>');
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
