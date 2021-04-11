<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Drjele\DoctrineAudit\Dto\Storage\EntityDto;
use Drjele\DoctrineAudit\Exception\Exception;

class DoctrineSchemaSubscriber implements EventSubscriber
{
    private const AUDIT_TRANSACTION_ID = 'audit_transaction_id';

    public function getSubscribedEvents()
    {
        return [
            ToolEvents::postGenerateSchemaTable,
            ToolEvents::postGenerateSchema,
        ];
    }

    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();

        $notSupportedInheritance = [
            ClassMetadataInfo::INHERITANCE_TYPE_NONE,
            ClassMetadataInfo::INHERITANCE_TYPE_JOINED,
            ClassMetadataInfo::INHERITANCE_TYPE_SINGLE_TABLE,
        ];
        if (!\in_array($classMetadata->inheritanceType, $notSupportedInheritance, true)) {
            throw new Exception(
                \sprintf('inheritance type "%s" is not yet supported', $classMetadata->inheritanceType)
            );
        }

        $schema = $eventArgs->getSchema();
        $entityTable = $eventArgs->getClassTable();

        /* remove original entity data */
        $schema->dropTable($entityTable->getName());

        $auditTable = $schema->createTable($entityTable->getName());

        foreach ($entityTable->getColumns() as $column) {
            /* @var Column $column */
            $auditTable->addColumn(
                $column->getName(),
                $column->getType()->getName(),
                \array_merge(
                    $column->toArray(),
                    ['notnull' => false, 'autoincrement' => false]
                )
            );
        }
        $auditTable->addColumn(static::AUDIT_TRANSACTION_ID, 'integer');
        $auditTable->addColumn(
            'audit_operation',
            'enum',
            [
                'columnDefinition' => \sprintf('ENUM("%s", "%s", "%s")', ...EntityDto::OPERATIONS),
            ]
        );

        $primaryKeyColumns = $entityTable->getPrimaryKey()->getColumns();
        $primaryKeyColumns[] = static::AUDIT_TRANSACTION_ID;
        $auditTable->setPrimaryKey($primaryKeyColumns);
        $transactionIndexName = static::AUDIT_TRANSACTION_ID . '_' . \md5($auditTable->getName()) . '_idx';
        $auditTable->addIndex([static::AUDIT_TRANSACTION_ID], $transactionIndexName);
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        /* @todo add transaction table */
    }
}
