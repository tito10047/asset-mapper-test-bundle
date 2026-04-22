<?php

namespace Tito10047\AssetMapperTestBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator\Runner;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator\Variant;

/**
 * Scaffolds a `package.json` (and optionally `tests/js/setup.mjs`) tailored to
 * the user-selected workflow variant and JS test runner.
 *
 * Interactive by default; both values may be supplied up-front via
 * `--variant` and `--runner` for non-interactive (CI) usage.
 */
#[AsCommand(
    name: 'asset-mapper-test:init',
    description: 'Scaffold package.json (and tests/js/setup.mjs) for JS testing',
)]
class InitCommand extends Command
{
    public function __construct(
        private readonly PackageJsonGenerator $generator,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('variant', null, InputOption::VALUE_REQUIRED, 'Workflow variant: symlink | loader')
            ->addOption('runner', null, InputOption::VALUE_REQUIRED, 'JS test runner: node | vitest')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing package.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $err = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $variantRaw = $input->getOption('variant');
        if ($variantRaw === null && $input->isInteractive()) {
            $variantRaw = $io->choice(
                'Which workflow variant do you want to use?',
                ['symlink', 'loader'],
                'loader',
            );
        }

        $runnerRaw = $input->getOption('runner');
        if ($runnerRaw === null && $input->isInteractive()) {
            $runnerRaw = $io->choice(
                'Which JS test runner do you want to use?',
                ['node', 'vitest'],
                'node',
            );
        }

        $variant = Variant::tryFrom((string) $variantRaw);
        $runner = Runner::tryFrom((string) $runnerRaw);

        if ($variant === null) {
            $err->writeln(sprintf('<error>Invalid variant "%s". Use "symlink" or "loader".</error>', (string) $variantRaw));
            return Command::FAILURE;
        }
        if ($runner === null) {
            $err->writeln(sprintf('<error>Invalid runner "%s". Use "node" or "vitest".</error>', (string) $runnerRaw));
            return Command::FAILURE;
        }

        $result = $this->generator->generate(
            $this->projectDir,
            $variant,
            $runner,
            (bool) $input->getOption('force'),
        );

        if ($result->packageJsonCreated) {
            $io->success(sprintf('Wrote %s', $result->packageJsonPath));
        } else {
            $io->warning(sprintf('%s already exists (use --force to overwrite).', $result->packageJsonPath));
        }

        if ($runner === Runner::Node && $result->setupMjsPath !== null) {
            if ($result->setupMjsCreated) {
                $io->writeln(sprintf('  → created <info>%s</info>', $result->setupMjsPath));
            } else {
                $io->writeln(sprintf('  → kept existing <comment>%s</comment>', $result->setupMjsPath));
            }
        }

        $io->writeln('');
        $io->writeln('Next steps:');
        $io->writeln('  1. <info>npm install</info>');
        $io->writeln('  2. Write tests into <info>tests/js/*.test.mjs</info>');
        $io->writeln('  3. <info>npm test</info>');

        return Command::SUCCESS;
    }
}
