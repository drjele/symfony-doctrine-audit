<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('drjele_doctrine_audit');

        $root = $treeBuilder->getRootNode()
            ->children();

        $this->attachStorages($root);

        $this->attachAuditors($root);

        return $treeBuilder;
    }

    private function attachStorages(NodeBuilder $root): void
    {
        $root->arrayNode('storages')->isRequired()
            ->cannotBeEmpty();
    }

    private function attachAuditors(NodeBuilder $root): void
    {
        $root->arrayNode('auditors')->isRequired()
            ->cannotBeEmpty()
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->children()
            ->scalarNode('name')->end()
            ->scalarNode('entity_manager')->end()
            ->scalarNode('storage')->end();
    }
}
