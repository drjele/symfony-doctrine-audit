<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const TYPE_DOCTRINE = 'doctrine';
    public const TYPE_FILE = 'file';
    public const TYPE_CUSTOM = 'custom';

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
        /** @var ArrayNodeDefinition $storages */
        $storages = $root->arrayNode('storages')->isRequired()
            ->cannotBeEmpty()
            ->useAttributeAsKey('name')
            ->prototype('array');

        $types = [static::TYPE_DOCTRINE, static::TYPE_FILE, static::TYPE_CUSTOM];

        /* @todo have different child nodes based on type */
        $storages->children()
            ->scalarNode('name')->end()
            ->enumNode('type')->values($types)->isRequired()->end()
            ->scalarNode('entity_manager')->end()/* for doctrine */
            ->scalarNode('connection')->end()/* for doctrine */
            ->scalarNode('file')->end()/* for file */
            ->scalarNode('service')->end()/* for custom */
            ->arrayNode('config')->scalarPrototype()->end()/* generic node with variable content for extra configs */
        ;
    }

    private function attachAuditors(NodeBuilder $root): void
    {
        /** @var ArrayNodeDefinition $auditors */
        $auditors = $root->arrayNode('auditors')->isRequired()
            ->cannotBeEmpty()
            ->useAttributeAsKey('name')
            ->prototype('array');

        $auditors->children()
            ->scalarNode('name')->end()
            ->scalarNode('entity_manager')->defaultValue('default')->end()
            ->scalarNode('connection')->end()/* will use the name of the entity manager if this is not set */
            ->scalarNode('storage')->isRequired()->end()
            ->scalarNode('transaction_provider')->isRequired()->end()
            ->scalarNode('logger')->end()
            ->arrayNode('ignored_fields')->scalarPrototype()->end();
    }
}
