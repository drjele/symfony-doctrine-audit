<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\DependencyInjection;

use Drjele\Doctrine\Audit\Exception\Exception;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const TYPE_DOCTRINE = 'doctrine';
    public const TYPE_FILE = 'file';
    public const TYPE_CUSTOM = 'custom';

    public function getConfigTreeBuilder(): TreeBuilder
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
            ->arrayPrototype();

        $types = [static::TYPE_DOCTRINE, static::TYPE_FILE, static::TYPE_CUSTOM];

        /* @todo have different child nodes based on type */
        $storages->children()
            ->scalarNode('name')->end()
            ->enumNode('type')->values($types)->isRequired()->end()
            ->scalarNode('entity_manager')->end()/* for doctrine */
            ->scalarNode('connection')->end()/* for doctrine */
            ->scalarNode('file')->end()/* for file */
            ->scalarNode('service')->end()/* for custom */
            ->arrayNode('config')->scalarPrototype()->end()->end(); /* generic node with variable content for extra configs */
    }

    private function attachAuditors(NodeBuilder $root): void
    {
        /** @var ArrayNodeDefinition $auditors */
        $auditors = $root->arrayNode('auditors')->isRequired()
            ->cannotBeEmpty()
            ->useAttributeAsKey('name')
            ->arrayPrototype();

        $auditors->beforeNormalization()->always(
            function (array $auditor) {
                $auditor['synchronous_storages'] ??= $auditor['storages'];

                if ($diff = \array_diff($auditor['synchronous_storages'], $auditor['storages'])) {
                    throw new Exception(
                        \sprintf(
                            'the synchronous storages `%s` were not found in the storages list `%s`',
                            \implode(', ', $diff),
                            \implode(', ', $auditor['storages'])
                        )
                    );
                }

                return $auditor;
            }
        );

        $auditors->children()
            ->scalarNode('name')->end()
            ->scalarNode('entity_manager')->defaultValue('default')->end()
            ->scalarNode('connection')->end()/* will use the name of the entity manager if this is not set */
            /* the complete list of storage used by the auditor */
            ->arrayNode('storages')->isRequired()->cannotBeEmpty()->scalarPrototype()->end()->end()
            /* a part of the storages list that are used on \Drjele\Doctrine\Audit\Auditor\Auditor::save */
            ->arrayNode('synchronous_storages')->cannotBeEmpty()->isRequired()->scalarPrototype()->end()->end()
            ->scalarNode('transaction_provider')->isRequired()->end()
            ->scalarNode('logger')->end()
            ->arrayNode('ignored_fields')->scalarPrototype()->end()->end();
    }
}
