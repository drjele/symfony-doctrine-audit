<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\DependencyInjection;

use Drjele\DoctrineAudit\Auditor\Auditor;
use Drjele\DoctrineAudit\Service\AnnotationService;
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

        $this->attachAuditors($container, $config['auditors']);
    }

    private function attachAuditors(ContainerBuilder $container, array $auditors): void
    {
        foreach ($auditors as $name => $auditor) {
            $entityManagerName = $auditor['entity_manager'];
            $storage = $auditor['storage'];

            $definition = new Definition(
                Auditor::class,
                [
                    new Reference(AnnotationService::class),
                    new Reference($entityManagerName),
                    new Reference($storage),
                ]
            );

            /* @todo add connection to em */
            $definition->addTag('doctrine.event_subscriber', ['connection' => 'default']);

            $id = \sprintf('drjele_doctrine_audit.auditor.%s', $name);

            $container->setDefinition($id, $definition);
        }
    }
}
