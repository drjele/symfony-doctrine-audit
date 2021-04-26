<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\DependencyInjection;

use Drjele\DoctrineAudit\Auditor\Auditor;
use Drjele\DoctrineAudit\Auditor\Config as AuditorConfig;
use Drjele\DoctrineAudit\Command\DoctrineSchema\CreateCommand;
use Drjele\DoctrineAudit\Command\DoctrineSchema\UpdateCommand;
use Drjele\DoctrineAudit\EventSubscriber\DoctrineSchemaSubscriber;
use Drjele\DoctrineAudit\Exception\Exception;
use Drjele\DoctrineAudit\Service\AnnotationReadService;
use Drjele\DoctrineAudit\Storage\Doctrine\Config as DoctrineConfig;
use Drjele\DoctrineAudit\Storage\Doctrine\Storage;
use Drjele\DoctrineAudit\Storage\FileStorage;
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

    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->defineStorages($container, $config['storages']);

        $this->defineAuditors($container, $config['auditors']);

        $this->defineServices($container, $config['auditors'], $config['storages']);
    }

    private function defineStorages(ContainerBuilder $container, array $storages): void
    {
        foreach ($storages as $name => $storage) {
            $type = $storage['type'];

            switch ($type) {
                case Configuration::TYPE_DOCTRINE:
                    $this->defineStorageDoctrine($container, $storage, $name);
                    break;
                case Configuration::TYPE_FILE:
                    $this->defineStorageFile($container, $storage, $name);
                    break;
                case Configuration::TYPE_CUSTOM:
                    $this->defineStorageCustom($container, $storage, $name);
                    break;
                default:
                    throw new Exception(\sprintf('invalid storage type "%s"', $type));
            }
        }
    }

    private function defineStorageDoctrine(ContainerBuilder $container, array $storage, string $name): void
    {
        $type = $storage['type'];
        [$entityManager,] = $this->getEntityManagerAndConnection($storage);

        if (empty($entityManager)) {
            throw new Exception(
                \sprintf('the "%s" config is mandatory for storage type "%s"', 'entity_manager', $type)
            );
        }

        $this->defineStorageDoctrineConfig($container, $name, $storage['config'] ?? []);

        $definition = new Definition(
            Storage::class,
            [
                $this->getEntityManager($entityManager),
                new Reference($this->getStorageConfigId($name)),
            ]
        );

        $storageServiceId = $this->getStorageId($name);

        $container->setDefinition($storageServiceId, $definition);
    }

    private function defineStorageDoctrineConfig(ContainerBuilder $container, string $name, array $config): void
    {
        $definition = new Definition(
            DoctrineConfig::class,
            [
                $config,
            ]
        );

        $storageServiceId = $this->getStorageConfigId($name);

        $container->setDefinition($storageServiceId, $definition);
    }

    private function defineStorageFile(ContainerBuilder $container, array $storage, string $name): void
    {
        $type = $storage['type'];
        $file = $storage['file'] ?? null;

        if (empty($file)) {
            throw new Exception(
                \sprintf('the "%s" config is mandatory for storage type "%s"', 'file', $type)
            );
        }

        $definition = new Definition(
            FileStorage::class,
            [
                $file,
            ]
        );

        $storageServiceId = $this->getStorageId($name);

        $container->setDefinition($storageServiceId, $definition);
    }

    private function defineStorageCustom(ContainerBuilder $container, array $storage, string $name): void
    {
        $type = $storage['type'];
        $service = $storage['service'] ?? null;

        if (empty($service)) {
            throw new Exception(
                \sprintf('the "%s" config is mandatory for storage type "%s"', 'service', $type)
            );
        }

        $storageServiceId = $this->getStorageId($name);

        $container->setAlias($storageServiceId, $service);
    }

    private function defineAuditors(ContainerBuilder $container, array $auditors): void
    {
        foreach ($auditors as $name => $auditor) {
            [$entityManager, $connection] = $this->getEntityManagerAndConnection($auditor);
            $storage = $auditor['storage'];
            $transactionProvider = $auditor['transaction_provider'];
            $logger = $auditor['logger'] ?? null;

            $this->defineAuditorConfig($container, $name, $auditor);

            $definition = new Definition(
                Auditor::class,
                [
                    new Reference($this->getAuditorConfigId($name)),
                    $this->getEntityManager($entityManager),
                    new Reference($this->getStorageId($storage)),
                    new Reference($transactionProvider),
                    null == $logger ? $logger : new Reference($logger),
                    new Reference(AnnotationReadService::class),
                ]
            );

            $definition->addTag('doctrine.event_subscriber', ['connection' => $connection]);

            $container->setDefinition($this->getAuditorId($name), $definition);
        }
    }

    private function defineAuditorConfig(ContainerBuilder $container, string $name, array $auditors): void
    {
        $definition = new Definition(
            AuditorConfig::class,
            [
                $auditors['ignored_fields'] ?? [],
            ]
        );

        $storageServiceId = $this->getAuditorConfigId($name);

        $container->setDefinition($storageServiceId, $definition);
    }

    private function defineServices(ContainerBuilder $container, array $auditors, array $storages): void
    {
        foreach ($auditors as $name => $auditor) {
            $storage = $storages[$auditor['storage']];

            $storageType = $storage['type'];

            switch ($storageType) {
                case Configuration::TYPE_DOCTRINE:
                    $this->defineSchemaCommands(
                        $container,
                        $name,
                        $auditor,
                        $storage
                    );
                    break;
            }
        }
    }

    private function defineSchemaCommands(
        ContainerBuilder $container,
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
            $container,
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

            $container->setDefinition(
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

        $container->setDefinition(
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
