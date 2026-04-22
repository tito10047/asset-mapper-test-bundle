<?php

namespace Tito10047\AssetMapperTestBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\AssetMapperTestBundle\Command\ExportImportmapCommand;
use Tito10047\AssetMapperTestBundle\Command\InitCommand;
use Tito10047\AssetMapperTestBundle\Command\SetupNodeModulesCommand;
use Tito10047\AssetMapperTestBundle\Setup\ImportmapJsonExporter;
use Tito10047\AssetMapperTestBundle\Setup\NodeModulesSetup;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator;

class AssetMapperTestExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register(Filesystem::class, Filesystem::class);

        $container->register(NodeModulesSetup::class, NodeModulesSetup::class)
            ->setArguments([
                '%asset_mapper_test.importmap_path%',
                '%asset_mapper_test.vendor_dir%',
                '%asset_mapper_test.project_dir%',
                new Reference(Filesystem::class),
                new Reference('monolog.logger.asset_mapper_test', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            ]);

        $container->register(SetupNodeModulesCommand::class, SetupNodeModulesCommand::class)
            ->setArguments([new Reference(NodeModulesSetup::class)])
            ->addTag('console.command');

        $container->register(ImportmapJsonExporter::class, ImportmapJsonExporter::class)
            ->setArguments([
                '%asset_mapper_test.importmap_path%',
                '%asset_mapper_test.vendor_dir%',
                '%asset_mapper_test.project_dir%',
                new Reference(Filesystem::class),
                new Reference('monolog.logger.asset_mapper_test', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            ]);

        $container->register(ExportImportmapCommand::class, ExportImportmapCommand::class)
            ->setArguments([new Reference(ImportmapJsonExporter::class)])
            ->addTag('console.command');

        $container->register(PackageJsonGenerator::class, PackageJsonGenerator::class)
            ->setArguments([new Reference(Filesystem::class)]);

        $container->register(InitCommand::class, InitCommand::class)
            ->setArguments([
                new Reference(PackageJsonGenerator::class),
                '%asset_mapper_test.project_dir%',
            ])
            ->addTag('console.command');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $frameworkConfigs = $container->getExtensionConfig('framework');

        $importmapPath = null;
        $vendorDir = null;

        foreach ($frameworkConfigs as $config) {
            if (isset($config['asset_mapper']['importmap_path'])) {
                $importmapPath = $config['asset_mapper']['importmap_path'];
            }
            if (isset($config['asset_mapper']['vendor_dir'])) {
                $vendorDir = $config['asset_mapper']['vendor_dir'];
            }
        }

        $projectDir = $container->getParameter('kernel.project_dir');

        $container->setParameter(
            'asset_mapper_test.importmap_path',
            $importmapPath ?? $projectDir . '/importmap.php'
        );
        $container->setParameter(
            'asset_mapper_test.vendor_dir',
            $vendorDir ?? $projectDir . '/assets/vendor'
        );
        $container->setParameter(
            'asset_mapper_test.project_dir',
            $projectDir
        );
    }
}
