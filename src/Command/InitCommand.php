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
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator\Deps;
use Symfony\Component\Process\Process;

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
            ->addOption('deps', null, InputOption::VALUE_REQUIRED, 'Dependency management: node_modules | asset_mapper')
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

        $depsRaw = $input->getOption('deps');
        if ($variant === Variant::Loader && $depsRaw === null && $input->isInteractive()) {
            $depsRaw = $io->choice(
                'How do you want to manage test dependencies (like happy-dom/vitest)?',
                ['node_modules', 'asset_mapper'],
                'node_modules',
            );
        }
        $deps = Deps::tryFrom((string) $depsRaw) ?? Deps::NodeModules;

        if ($runner === Runner::Vitest && $deps === Deps::AssetMapper) {
            $err->writeln('<error>Using Vitest with AssetMapper dependencies is not supported because Vitest has complex internal dependencies that cannot be reliably bundled.</error>');
            if ($input->isInteractive()) {
                $fallbackNode = 'Use Node.js built-in test runner (runner=node)';
                $fallbackNpm = 'Manage test dependencies via npm (deps=node_modules)';
                $fallback = $io->choice(
                    'How would you like to proceed?',
                    [$fallbackNode, $fallbackNpm]
                );
                
                if ($fallback === $fallbackNode) {
                    $runner = Runner::Node;
                } else {
                    $deps = Deps::NodeModules;
                }
            } else {
                return Command::FAILURE;
            }
        }

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
            $deps,
            (bool) $input->getOption('force'),
        );

        if ($deps === Deps::AssetMapper) {
            $io->writeln('Adding test dependencies to AssetMapper...');
            $packages = ['happy-dom'];
            if ($runner === Runner::Vitest) {
                $packages[] = 'vitest';
            }
            $process = new Process(['php', 'bin/console', 'importmap:require', ...$packages], $this->projectDir);
            $process->run(function ($type, $buffer) use ($io) {
                $io->write($buffer);
            });
            
            if (!$process->isSuccessful()) {
                $err->writeln('<error>Failed to add dependencies to AssetMapper.</error>');
            }
        }

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

        if ($runner === Runner::Vitest && $result->vitestConfigPath !== null) {
            if ($result->vitestConfigCreated) {
                $io->writeln(sprintf('  → created <info>%s</info>', $result->vitestConfigPath));
            } else {
                $io->writeln(sprintf('  → kept existing <comment>%s</comment>', $result->vitestConfigPath));
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
