<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Command\DoctrineSchema;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;

abstract class AbstractCommand extends \Drjele\SymfonyCommand\Command\AbstractCommand implements EventSubscriber
{
    protected EntityManagerInterface $sourceEntityManager;
    protected EntityManagerInterface $destinationEntityManager;

    public function __construct(
        string $name,
        EntityManagerInterface $sourceEntityManager,
        EntityManagerInterface $destinationEntityManager
    ) {
        parent::__construct($name);

        $this->sourceEntityManager = $sourceEntityManager;
        $this->destinationEntityManager = $destinationEntityManager;
    }

    public function getSubscribedEvents()
    {
        return [
            ToolEvents::postGenerateSchemaTable,
            ToolEvents::postGenerateSchema,
        ];
    }

    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $eventArgs): void
    {
        $schema = $eventArgs->getSchema();
        $entityTable = $eventArgs->getClassTable();

        $schema->dropTable($entityTable->getName());

        /* @todo recreate table */
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        /* @todo add transaction table */
    }

    protected function createSchemaTool(): SchemaTool
    {
        /** @todo only consider audited entities */
        $sourceMetadatas = $this->sourceEntityManager->getMetadataFactory()->getAllMetadata();

        foreach ($sourceMetadatas as $classMetadata) {
            $this->destinationEntityManager->getMetadataFactory()
                ->setMetadataFor($classMetadata->getName(), $classMetadata);
        }

        return new SchemaTool($this->destinationEntityManager);
    }
}
