<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\DependencyInjection;

use Drjele\DoctrineAudit\Auditor\Auditor;
use Drjele\DoctrineAudit\Service\AnnotationReadService;
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
            $class = $storage['class'];

            $definition = new Definition($class);

            $container->setDefinition($this->getStorageId($name), $definition);
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
