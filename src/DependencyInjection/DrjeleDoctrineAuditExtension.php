<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\DependencyInjection;

use Drjele\DoctrineAudit\Auditor\Auditor;
use Drjele\DoctrineAudit\Command\CreateCommand;
use Drjele\DoctrineAudit\Command\UpdateCommand;
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
                    $this->createStorageDoctrine($container, $storage, $name);
                    break;
                case Configuration::TYPE_FILE:
                    $this->createStorageFile($container, $storage, $name);
                    break;
                case Configuration::TYPE_CUSTOM:
                    $this->createStorageCustom($container, $storage, $name);
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

            $type = $storage['type'];

            if (Configuration::TYPE_DOCTRINE !== $type) {
                continue;
            }

            $this->createDoctrineSchemaCommands(
                $container,
                $name,
                $auditor['entity_manager'],
                $storage['entity_manager']
            );
        }
    }

    private function createDoctrineSchemaCommands(
        ContainerBuilder $container,
        string $name,
        string $sourceEntityManager,
        string $destinationEntityManager
    ): void {
        $sourceEntityManagerReference = $this->getEntityManager($sourceEntityManager);
        $destinationEntityManagerReference = $this->getEntityManager($destinationEntityManager);

        $definition = new Definition(
            CreateCommand::class,
            [
                \sprintf('drjele-doctrine-audit:create:%s', $name),
                $sourceEntityManagerReference,
                $destinationEntityManagerReference,
            ]
        );

        $container->setDefinition($this->getCommandId(\sprintf('create.%s', $name)), $definition);

        $definition = new Definition(
            UpdateCommand::class,
            [
                \sprintf('drjele-doctrine-audit:update:%s', $name),
                $sourceEntityManagerReference,
                $destinationEntityManagerReference,
            ]
        );

        $container->setDefinition($this->getCommandId(\sprintf('update.%s', $name)), $definition);
    }

    private function createStorageDoctrine(ContainerBuilder $container, array $storage, string $name): void
    {
        $type = $storage['type'];
        $entityManager = $storage['entity_manager'] ?? null;

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

        $storageServiceId = $this->getStorageId($name);

        $container->setDefinition($storageServiceId, $definition);
    }

    private function createStorageFile(ContainerBuilder $container, array $storage, string $name): void
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

    private function createStorageCustom(ContainerBuilder $container, array $storage, string $name): void
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
        return \sprintf('drjele_doctrine_audit.storage.%s', $name);
    }

    private function getAuditorId(string $name): string
    {
        return \sprintf('drjele_doctrine_audit.auditor.%s', $name);
    }

    private function getCommandId(string $name): string
    {
        return \sprintf('drjele_doctrine_audit.command.%s', $name);
    }

    private function getEntityManager(string $name): Reference
    {
        /* @todo get from doctrine */
        return new Reference(\sprintf('doctrine.orm.%s_entity_manager', $name));
    }
}
