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

        $this->attachAuditors($root);

        return $treeBuilder;
    }

    private function attachAuditors(NodeBuilder $root): void
    {
        $root->arrayNode('auditors')->isRequired()
            ->cannotBeEmpty()
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->children()
            ->scalarNode('name')->end()
            ->scalarNode('storage')->end();
    }
}
