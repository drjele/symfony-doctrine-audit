<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\DependencyInjection;

use Drjele\DoctrineAudit\Auditor\Auditor;
use Drjele\DoctrineAudit\Command\DoctrineSchema\CreateCommand;
use Drjele\DoctrineAudit\Command\DoctrineSchema\UpdateCommand;
use Drjele\DoctrineAudit\Exception\Exception;
use Drjele\DoctrineAudit\Service\AnnotationReadService;
use Drjele\DoctrineAudit\Storage\DoctrineStorage;
use Drjele\DoctrineAudit\Storage\FileStorage;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class DrjeleDoctrineAuditExtension extends Extension
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

        $this->defineCommands($container, $config['auditors'], $config['storages']);
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

    private function defineAuditors(ContainerBuilder $container, array $auditors): void
    {
        foreach ($auditors as $name => $auditor) {
            $entityManager = $auditor['entity_manager'];
            $connection = $auditor['connection'] ?? $entityManager;
            $storage = $auditor['storage'];
            $transactionProvider = $auditor['transaction_provider'];
            $logger = $auditor['logger'] ?? null;

            $definition = new Definition(
                Auditor::class,
                [
                    new Reference(AnnotationReadService::class),
                    $this->getEntityManager($entityManager),
                    new Reference($this->getStorageId($storage)),
                    new Reference($transactionProvider),
                    null == $logger ? $logger : new Reference($logger),
                ]
            );

            $definition->addTag('doctrine.event_subscriber', ['connection' => $connection]);

            $container->setDefinition($this->getAuditorId($name), $definition);
        }
    }

    private function defineCommands(ContainerBuilder $container, array $auditors, array $storages): void
    {
        foreach ($auditors as $name => $auditor) {
            $storage = $storages[$auditor['storage']];

            $storageType = $storage['type'];

            switch ($storageType) {
                case Configuration::TYPE_DOCTRINE:
                    $this->defineSchemaCommands(
                        $container,
                        $name,
                        $auditor['entity_manager'],
                        $storage['entity_manager']
                    );
                    break;
            }
        }
    }

    private function defineSchemaCommands(
        ContainerBuilder $container,
        string $name,
        string $sourceEntityManager,
        string $destinationEntityManager
    ): void {
        $sourceEntityManagerReference = $this->getEntityManager($sourceEntityManager);
        $destinationEntityManagerReference = $this->getEntityManager($destinationEntityManager);

        $createCommandDefinition = new Definition(
            CreateCommand::class,
            [
                \sprintf('%s:schema:create:%s', static::BASE_COMMAND_NAME, $name),
                $sourceEntityManagerReference,
                $destinationEntityManagerReference,
            ]
        );

        $createCommandDefinition->addTag('console.command');

        $container->setDefinition($this->getCommandId(\sprintf('create.%s', $name)), $createCommandDefinition);

        $updateCommandDefinition = new Definition(
            UpdateCommand::class,
            [
                \sprintf('%s:schema:update:%s', static::BASE_COMMAND_NAME, $name),
                $sourceEntityManagerReference,
                $destinationEntityManagerReference,
            ]
        );

        $updateCommandDefinition->addTag('console.command');

        $container->setDefinition($this->getCommandId(\sprintf('update.%s', $name)), $updateCommandDefinition);
    }

    private function defineStorageDoctrine(ContainerBuilder $container, array $storage, string $name): void
    {
        $type = $storage['type'];
        $entityManager = $storage['entity_manager'] ?? null;
        $connection = $storage['connection'] ?? $entityManager;

        if (empty($entityManager)) {
            throw new Exception(
                \sprintf('the "%s" config is mandatory for storage type "%s"', 'entity_manager', $type)
            );
        }

        $definition = new Definition(
            DoctrineStorage::class,
            [
                $this->getEntityManager($entityManager),
            ]
        );

        $definition->addTag('doctrine.event_subscriber', ['connection' => $connection]);

        $storageServiceId = $this->getStorageId($name);

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
        $service = $storage['service'];

        if (empty($service)) {
            throw new Exception(
                \sprintf('the "%s" config is mandatory for storage type "%s"', 'service', $type)
            );
        }

        $storageServiceId = $this->getStorageId($name);

        $container->setAlias($storageServiceId, $service);
    }

    private function getStorageId(string $name): string
    {
        return \sprintf('%s.storage.%s', static::BASE_SERVICE_ID, $name);
    }

    private function getAuditorId(string $name): string
    {
        return \sprintf('%s.auditor.%s', static::BASE_SERVICE_ID, $name);
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
}
