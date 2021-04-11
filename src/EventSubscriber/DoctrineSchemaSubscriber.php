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
    /** @todo move to config */
    private const AUDIT_TRANSACTION = 'audit_transaction';
    private const AUDIT_TRANSACTION_ID = 'audit_transaction_id';
    private const AUDIT_TRANSACTION_ID_TYPE = 'integer';

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
        $auditTable->addColumn(static::AUDIT_TRANSACTION_ID, static::AUDIT_TRANSACTION_ID_TYPE);
        $auditTable->addColumn(
            'audit_operation',
            'string',
            [
                'columnDefinition' => \sprintf('ENUM("%s", "%s", "%s")', ...EntityDto::OPERATIONS),
            ]
        );

        $primaryKeyColumns = $entityTable->getPrimaryKey()->getColumns();
        $primaryKeyColumns[] = static::AUDIT_TRANSACTION_ID;
        $auditTable->setPrimaryKey($primaryKeyColumns);

        $auditTable->addIndex([static::AUDIT_TRANSACTION_ID], static::AUDIT_TRANSACTION_ID)
            ->addForeignKeyConstraint(
                static::AUDIT_TRANSACTION,
                [static::AUDIT_TRANSACTION_ID],
                ['id'],
                ['onDelete' => 'RESTRICT']
            );
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        $schema = $eventArgs->getSchema();

        $transactionTable = $schema->createTable(static::AUDIT_TRANSACTION);

        $transactionTable->addColumn(
            'id',
            static::AUDIT_TRANSACTION_ID_TYPE,
            [
                'autoincrement' => true,
            ]
        );
        $transactionTable->addColumn('username', 'string')->setNotnull(false);
        $transactionTable->addColumn('created', 'datetime');

        $transactionTable->setPrimaryKey(['id']);
    }
}
