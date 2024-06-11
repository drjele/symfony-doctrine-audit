<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\DependencyInjection;

use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\ToolEvents;
use Drjele\Doctrine\Audit\Auditor\Auditor;
use Drjele\Doctrine\Audit\Auditor\Configuration as AuditorConfig;
use Drjele\Doctrine\Audit\Command\DoctrineSchema\CreateCommand;
use Drjele\Doctrine\Audit\Command\DoctrineSchema\UpdateCommand;
use Drjele\Doctrine\Audit\EventSubscriber\DoctrineSchemaListener;
use Drjele\Doctrine\Audit\Exception\Exception;
use Drjele\Doctrine\Audit\Service\AnnotationReadService;
use Drjele\Doctrine\Audit\Storage\Doctrine\Configuration as DoctrineConfig;
use Drjele\Doctrine\Audit\Storage\Doctrine\Storage;
use Drjele\Doctrine\Audit\Storage\FileStorage;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class DrjeleDoctrineAuditExtension extends Extension
{
    private const BASE_COMMAND_NAME = 'drjele:doctrine:audit';
    private const BASE_SERVICE_ID = 'drjele_doctrine_audit';

    public function load(array $configs, ContainerBuilder $containerBuilder): void
    {
        $loader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->defineStorages($containerBuilder, $config['storages']);

        $this->defineAuditors($containerBuilder, $config['auditors']);

        $this->defineServices($containerBuilder, $config['auditors'], $config['storages']);
    }

    private function defineStorages(ContainerBuilder $containerBuilder, array $storages): void
    {
        foreach ($storages as $storageName => $storage) {
            $type = $storage['type'];

            switch ($type) {
                case Configuration::TYPE_DOCTRINE:
                    $this->defineStorageDoctrine($containerBuilder, $storage, $storageName);
                    break;
                case Configuration::TYPE_FILE:
                    $this->defineStorageFile($containerBuilder, $storage, $storageName);
                    break;
                case Configuration::TYPE_CUSTOM:
                    $this->defineStorageCustom($containerBuilder, $storage, $storageName);
                    break;
                default:
                    throw new Exception(\sprintf('invalid storage type `%s`', $type));
            }
        }
    }

    private function defineStorageDoctrine(
        ContainerBuilder $containerBuilder,
        array $storage,
        string $storageName
    ): void {
        $type = $storage['type'];
        [$entityManager] = $this->getEntityManagerAndConnection($storage);

        if (empty($entityManager)) {
            throw new Exception(
                \sprintf('the `%s` config is mandatory for storage type `%s`', 'entity_manager', $type)
            );
        }

        $this->defineStorageDoctrineConfig($containerBuilder, $storageName, $storage['config'] ?? []);

        $logger = $auditor['logger'] ?? null;

        $definition = new Definition(
            Storage::class,
            [
                $this->getEntityManager($entityManager),
                new Reference($this->getStorageConfigId($storageName)),
                null === $logger ? $logger : new Reference($logger),
            ]
        );

        $storageServiceId = $this->getStorageId($storageName);

        $containerBuilder->setDefinition($storageServiceId, $definition);
    }

    private function defineStorageDoctrineConfig(
        ContainerBuilder $containerBuilder,
        string $storageName,
        array $config
    ): void {
        $definition = new Definition(
            DoctrineConfig::class,
            [
                $config,
            ]
        );

        $storageServiceId = $this->getStorageConfigId($storageName);

        $containerBuilder->setDefinition($storageServiceId, $definition);
    }

    private function defineStorageFile(
        ContainerBuilder $containerBuilder,
        array $storage,
        string $storageName
    ): void {
        $type = $storage['type'];
        $file = $storage['file'] ?? null;

        if (empty($file)) {
            throw new Exception(
                \sprintf('the `%s` config is mandatory for storage type `%s`', 'file', $type)
            );
        }

        $logger = $auditor['logger'] ?? null;

        $definition = new Definition(
            FileStorage::class,
            [
                $file,
                null === $logger ? $logger : new Reference($logger),
            ]
        );

        $storageServiceId = $this->getStorageId($storageName);

        $containerBuilder->setDefinition($storageServiceId, $definition);
    }

    private function defineStorageCustom(
        ContainerBuilder $containerBuilder,
        array $storage,
        string $storageName
    ): void {
        $type = $storage['type'];
        $service = $storage['service'] ?? null;

        if (empty($service)) {
            throw new Exception(
                \sprintf('the `%s` config is mandatory for storage type `%s`', 'service', $type)
            );
        }

        $storageServiceId = $this->getStorageId($storageName);

        $containerBuilder->setAlias($storageServiceId, $service);
    }

    private function defineAuditors(ContainerBuilder $containerBuilder, array $auditors): void
    {
        foreach ($auditors as $auditorName => $auditor) {
            [$entityManager, $connection] = $this->getEntityManagerAndConnection($auditor);

            $transactionProvider = $auditor['transaction_provider'];
            $logger = $auditor['logger'] ?? null;

            $this->defineAuditorConfig($containerBuilder, $auditorName, $auditor);

            $storages = \array_map(
                fn(string $storage) => new Reference($this->getStorageId($storage)),
                $auditor['synchronous_storages']
            );

            $definition = new Definition(
                Auditor::class,
                [
                    new Reference($this->getAuditorConfigId($auditorName)),
                    $this->getEntityManager($entityManager),
                    $storages,
                    new Reference($transactionProvider),
                    null === $logger ? $logger : new Reference($logger),
                    new Reference(AnnotationReadService::class),
                ]
            );

            $definition->addTag('doctrine.event_listener', ['connection' => $connection, 'event' => Events::onFlush])
                ->addTag('doctrine.event_listener', ['connection' => $connection, 'event' => Events::postFlush]);

            $containerBuilder->setDefinition($this->getAuditorId($auditorName), $definition);
        }
    }

    private function defineAuditorConfig(ContainerBuilder $containerBuilder, string $auditorName, array $auditor): void
    {
        $definition = new Definition(
            AuditorConfig::class,
            [
                $auditor['ignored_fields'] ?? [],
            ]
        );

        $storageServiceId = $this->getAuditorConfigId($auditorName);

        $containerBuilder->setDefinition($storageServiceId, $definition);
    }

    private function defineServices(ContainerBuilder $containerBuilder, array $auditors, array $storages): void
    {
        foreach ($auditors as $auditorName => $auditor) {
            foreach ($auditor['storages'] as $storageName) {
                if (!isset($storages[$storageName])) {
                    throw new Exception(
                        \sprintf('could not find storage `%s` for auditor `%s`', $storageName, $auditorName)
                    );
                }

                $storage = $storages[$storageName];

                switch ($storage['type']) {
                    case Configuration::TYPE_DOCTRINE:
                        $this->defineSchemaCommands(
                            $containerBuilder,
                            $auditorName,
                            $storageName,
                            $auditor,
                            $storage
                        );
                        break;
                }
            }
        }
    }

    private function defineSchemaCommands(
        ContainerBuilder $containerBuilder,
        string $auditorName,
        string $storageName,
        array $auditor,
        array $storage
    ): void {
        [$auditorEntityManager] = $this->getEntityManagerAndConnection($auditor);
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

        $definition = new Definition(
            DoctrineSchemaListener::class,
            [
                new Reference(AnnotationReadService::class),
                new Reference($this->getAuditorConfigId($auditorName)),
                new Reference($this->getStorageConfigId($storageName)),
            ]
        );

        $definition->addTag('doctrine.event_listener', ['connection' => $storageConnection, 'event' => ToolEvents::postGenerateSchemaTable])
            ->addTag('doctrine.event_listener', ['connection' => $storageConnection, 'event' => ToolEvents::postGenerateSchema]);

        $containerBuilder->setDefinition(
            $this->getCommandId(\sprintf('schema:listener.%s.%s', $auditorName, $storageName)),
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
