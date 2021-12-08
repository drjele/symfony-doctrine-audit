<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\DependencyInjection;

use Drjele\Doctrine\Audit\Auditor\Auditor;
use Drjele\Doctrine\Audit\Auditor\Configuration as AuditorConfig;
use Drjele\Doctrine\Audit\Command\DoctrineSchema\CreateCommand;
use Drjele\Doctrine\Audit\Command\DoctrineSchema\UpdateCommand;
use Drjele\Doctrine\Audit\EventSubscriber\DoctrineSchemaSubscriber;
use Drjele\Doctrine\Audit\Exception\Exception;
use Drjele\Doctrine\Audit\Service\AnnotationReadService;
use Drjele\Doctrine\Audit\Storage\Doctrine\Configuration as DoctrineConfig;
use Drjele\Doctrine\Audit\Storage\Doctrine\Storage;
use Drjele\Doctrine\Audit\Storage\FileStorage;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class DrjeleDoctrineAuditExtension extends Extension
{
    private const BASE_COMMAND_NAME = 'drjele:doctrine:audit';
    private const BASE_SERVICE_ID = 'drjele_doctrine_audit';

    public function load(array $configs, ContainerBuilder $containerBuilder): void
    {
        $loader = new YamlFileLoader(
            $containerBuilder,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->defineStorages($containerBuilder, $config['storages']);

        $this->defineAuditors($containerBuilder, $config['auditors']);

        $this->defineServices($containerBuilder, $config['auditors'], $config['storages']);
    }

    private function defineStorages(ContainerBuilder $containerBuilder, array $storages): void
    {
        foreach ($storages as $name => $storage) {
            $type = $storage['type'];

            switch ($type) {
                case Configuration::TYPE_DOCTRINE:
                    $this->defineStorageDoctrine($containerBuilder, $storage, $name);
                    break;
                case Configuration::TYPE_FILE:
                    $this->defineStorageFile($containerBuilder, $storage, $name);
                    break;
                case Configuration::TYPE_CUSTOM:
                    $this->defineStorageCustom($containerBuilder, $storage, $name);
                    break;
                default:
                    throw new Exception(\sprintf('invalid storage type `%s`', $type));
            }
        }
    }

    private function defineStorageDoctrine(
        ContainerBuilder $containerBuilder,
        array $storage,
        string $name
    ): void {
        $type = $storage['type'];
        [$entityManager,] = $this->getEntityManagerAndConnection($storage);

        if (empty($entityManager)) {
            throw new Exception(
                \sprintf('the `%s` config is mandatory for storage type `%s`', 'entity_manager', $type)
            );
        }

        $this->defineStorageDoctrineConfig($containerBuilder, $name, $storage['config'] ?? []);

        $definition = new Definition(
            Storage::class,
            [
                $this->getEntityManager($entityManager),
                new Reference($this->getStorageConfigId($name)),
            ]
        );

        $storageServiceId = $this->getStorageId($name);

        $containerBuilder->setDefinition($storageServiceId, $definition);
    }

    private function defineStorageDoctrineConfig(
        ContainerBuilder $containerBuilder,
        string $name,
        array $config
    ): void {
        $definition = new Definition(
            DoctrineConfig::class,
            [
                $config,
            ]
        );

        $storageServiceId = $this->getStorageConfigId($name);

        $containerBuilder->setDefinition($storageServiceId, $definition);
    }

    private function defineStorageFile(
        ContainerBuilder $containerBuilder,
        array $storage,
        string $name
    ): void {
        $type = $storage['type'];
        $file = $storage['file'] ?? null;

        if (empty($file)) {
            throw new Exception(
                \sprintf('the `%s` config is mandatory for storage type `%s`', 'file', $type)
            );
        }

        $definition = new Definition(
            FileStorage::class,
            [
                $file,
            ]
        );

        $storageServiceId = $this->getStorageId($name);

        $containerBuilder->setDefinition($storageServiceId, $definition);
    }

    private function defineStorageCustom(
        ContainerBuilder $containerBuilder,
        array $storage,
        string $name
    ): void {
        $type = $storage['type'];
        $service = $storage['service'] ?? null;

        if (empty($service)) {
            throw new Exception(
                \sprintf('the `%s` config is mandatory for storage type `%s`', 'service', $type)
            );
        }

        $storageServiceId = $this->getStorageId($name);

        $containerBuilder->setAlias($storageServiceId, $service);
    }

    private function defineAuditors(ContainerBuilder $containerBuilder, array $auditors): void
    {
        foreach ($auditors as $name => $auditor) {
            [$entityManager, $connection] = $this->getEntityManagerAndConnection($auditor);
            $storage = $auditor['storage'];
            $transactionProvider = $auditor['transaction_provider'];
            $logger = $auditor['logger'] ?? null;

            $this->defineAuditorConfig($containerBuilder, $name, $auditor);

            $definition = new Definition(
                Auditor::class,
                [
                    new Reference($this->getAuditorConfigId($name)),
                    $this->getEntityManager($entityManager),
                    new Reference($this->getStorageId($storage)),
                    new Reference($transactionProvider),
                    null === $logger ? $logger : new Reference($logger),
                    new Reference(AnnotationReadService::class),
                ]
            );

            $definition->addTag('doctrine.event_subscriber', ['connection' => $connection]);

            $containerBuilder->setDefinition($this->getAuditorId($name), $definition);
        }
    }

    private function defineAuditorConfig(ContainerBuilder $containerBuilder, string $name, array $auditors): void
    {
        $definition = new Definition(
            AuditorConfig::class,
            [
                $auditors['ignored_fields'] ?? [],
            ]
        );

        $storageServiceId = $this->getAuditorConfigId($name);

        $containerBuilder->setDefinition($storageServiceId, $definition);
    }

    private function defineServices(ContainerBuilder $containerBuilder, array $auditors, array $storages): void
    {
        foreach ($auditors as $name => $auditor) {
            $storage = $storages[$auditor['storage']];

            $storageType = $storage['type'];

            switch ($storageType) {
                case Configuration::TYPE_DOCTRINE:
                    $this->defineSchemaCommands(
                        $containerBuilder,
                        $name,
                        $auditor,
                        $storage
                    );
                    break;
            }
        }
    }

    private function defineSchemaCommands(
        ContainerBuilder $containerBuilder,
        string $auditorName,
        array $auditor,
        array $storage
    ): void {
        [$auditorEntityManager,] = $this->getEntityManagerAndConnection($auditor);
        [$storageEntityManager, $storageConnection] = $this->getEntityManagerAndConnection($storage);

        $auditorEntityManagerReference = $this->getEntityManager($auditorEntityManager);
        $storageEntityManagerReference = $this->getEntityManager($storageEntityManager);

        $defineCommand = function (
            string $commandClass,
            string $commandName
        ) use (
            $containerBuilder,
            $auditorName,
            $auditorEntityManagerReference,
            $storageEntityManagerReference
        ): void {
            $definition = new Definition(
                $commandClass,
                [
                    \sprintf('%s:schema:%s:%s', static::BASE_COMMAND_NAME, $commandName, $auditorName),
                    $auditorEntityManagerReference,
                    $storageEntityManagerReference,
                ]
            );

            $definition->addTag('console.command');

            $containerBuilder->setDefinition(
                $this->getCommandId(\sprintf('%s.%s', $commandName, $auditorName)),
                $definition
            );
        };

        $defineCommand(CreateCommand::class, 'create');

        $defineCommand(UpdateCommand::class, 'update');

        $storageName = $auditor['storage'];

        $definition = new Definition(
            DoctrineSchemaSubscriber::class,
            [
                new Reference(AnnotationReadService::class),
                new Reference($this->getAuditorConfigId($auditorName)),
                new Reference($this->getStorageConfigId($storageName)),
            ]
        );

        $definition->addTag('doctrine.event_subscriber', ['connection' => $storageConnection]);

        $containerBuilder->setDefinition(
            $this->getCommandId(\sprintf('schema:subscriber.%s.%s', $auditorName, $storageName)),
            $definition
        );
    }

    private function getStorageId(string $name): string
    {
        return \sprintf('%s.storage.%s', static::BASE_SERVICE_ID, $name);
    }

    private function getStorageConfigId(string $name): string
    {
        return \sprintf('%s.storage.%s.config', static::BASE_SERVICE_ID, $name);
    }

    private function getAuditorId(string $name): string
    {
        return \sprintf('%s.auditor.%s', static::BASE_SERVICE_ID, $name);
    }

    private function getAuditorConfigId(string $name): string
    {
        return \sprintf('%s.auditor.%s.config', static::BASE_SERVICE_ID, $name);
    }

    private function getCommandId(string $name): string
    {
        return \sprintf('%s.command.%s', static::BASE_SERVICE_ID, $name);
    }

    private function getEntityManager(string $name): Reference
    {
        /* @todo get from doctrine */
        return new Reference(\sprintf('doctrine.orm.%s_entity_manager', $name));
    }

    private function getEntityManagerAndConnection(array $config): array
    {
        $entityManager = $config['entity_manager'];
        $connection = $config['connection'] ?? $entityManager;

        return [$entityManager, $connection];
    }
}
