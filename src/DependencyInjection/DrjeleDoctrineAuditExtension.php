<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\DependencyInjection;

use Drjele\DoctrineAudit\Auditor\Auditor;
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
    }

    private function defineStorages(ContainerBuilder $container, array $storages): void
    {
        foreach ($storages as $name => $storage) {
            $type = $storage['type'];
            $storageServiceId = $this->getStorageId($name);

            switch ($type) {
                case Configuration::TYPE_DOCTRINE:
                    $entityManager = $storage['entity_manager'];

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

                    $container->setDefinition($storageServiceId, $definition);
                    break;
                case Configuration::TYPE_FILE:
                    $file = $storage['file'];

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

                    $container->setDefinition($storageServiceId, $definition);
                    break;
                case Configuration::TYPE_CUSTOM:
                    $service = $storage['service'];

                    if (empty($service)) {
                        throw new Exception(
                            \sprintf('the "%s" config is mandatory for storage type "%s"', 'service', $type)
                        );
                    }

                    $container->setAlias($storageServiceId, $service);
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
            $userProvider = $auditor['user_provider'];

            $definition = new Definition(
                Auditor::class,
                [
                    new Reference(AnnotationReadService::class),
                    $this->getEntityManager($entityManager),
                    new Reference($this->getStorageId($storage)),
                    new Reference($userProvider),
                ]
            );

            $definition->addTag('doctrine.event_subscriber', ['connection' => $connection]);

            $container->setDefinition($this->getAuditorId($name), $definition);
        }
    }

    private function getStorageId(string $name): string
    {
        return \sprintf('drjele_doctrine_audit.storage.%s', $name);
    }

    private function getAuditorId(string $name): string
    {
        return \sprintf('drjele_doctrine_audit.auditor.%s', $name);
    }

    private function getEntityManager(string $name): Reference
    {
        /* @todo get from doctrine */
        return new Reference(\sprintf('doctrine.orm.%s_entity_manager', $name));
    }
}
