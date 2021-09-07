<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Drjele\Doctrine\Audit\Auditor\Configuration as AuditorConfiguration;
use Drjele\Doctrine\Audit\Dto\Storage\EntityDto;
use Drjele\Doctrine\Audit\Exception\Exception;
use Drjele\Doctrine\Audit\Service\AnnotationReadService;
use Drjele\Doctrine\Audit\Storage\Doctrine\Configuration as StorageConfiguration;

final class DoctrineSchemaSubscriber implements EventSubscriber
{
    private AnnotationReadService $annotationReadService;
    private AuditorConfiguration $auditorConfiguration;
    private StorageConfiguration $storageConfiguration;

    public function __construct(
        AnnotationReadService $annotationReadService,
        AuditorConfiguration $auditorConfiguration,
        StorageConfiguration $storageConfiguration
    ) {
        $this->annotationReadService = $annotationReadService;
        $this->auditorConfiguration = $auditorConfiguration;
        $this->storageConfiguration = $storageConfiguration;
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
        $classMetadata = $eventArgs->getClassMetadata();
        $schema = $eventArgs->getSchema();
        $entityTable = $eventArgs->getClassTable();

        /* remove original entity data */
        $schema->dropTable($entityTable->getName());

        $entityDto = $this->annotationReadService->buildEntityDto($classMetadata);
        if (null === $entityDto) {
            return;
        }

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

        $auditTable = $schema->createTable($entityTable->getName());

        $auditedColums = 0;
        foreach ($entityTable->getColumns() as $column) {
            $columnName = $column->getName();

            $field = $classMetadata->getFieldForColumn($columnName);
            if (\in_array($field, $entityDto->getIgnoredFields(), true)) {
                continue;
            }

            if (\in_array($field, $this->auditorConfiguration->getIgnoredFields(), true)) {
                continue;
            }

            /* @var Column $column */
            $auditTable->addColumn(
                $columnName,
                $column->getType()->getName(),
                \array_merge(
                    $column->toArray(),
                    ['notnull' => false, 'autoincrement' => false]
                )
            );

            ++$auditedColums;
        }

        if (0 === $auditedColums) {
            return;
        }

        $auditTable->addColumn(
            $this->storageConfiguration->getTransactionIdColumnName(),
            $this->storageConfiguration->getTransactionIdColumnType()
        );
        $auditTable->addColumn(
            'audit_operation',
            'string',
            [
                'columnDefinition' => \sprintf('ENUM("%s", "%s", "%s")', ...EntityDto::OPERATIONS),
            ]
        );

        $primaryKeyColumns = $entityTable->getPrimaryKey()->getColumns();
        $primaryKeyColumns[] = $this->storageConfiguration->getTransactionIdColumnName();
        $auditTable->setPrimaryKey($primaryKeyColumns);

        $auditTable->addIndex(
            [$this->storageConfiguration->getTransactionIdColumnName()],
            $this->storageConfiguration->getTransactionIdColumnName()
        );
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        $schema = $eventArgs->getSchema();

        $transactionTable = $schema->createTable(
            $this->storageConfiguration->getTransactionTableName()
        );

        $transactionTable->addColumn(
            'id',
            $this->storageConfiguration->getTransactionIdColumnType(),
            [
                'autoincrement' => true,
            ]
        );
        $transactionTable->addColumn('username', 'string')->setNotnull(false);
        $transactionTable->addColumn('created', 'datetime');

        $transactionTable->setPrimaryKey(['id']);

        foreach ($schema->getTables() as $table) {
            if ($table->getName() === $this->storageConfiguration->getTransactionTableName()) {
                continue;
            }

            $table->addForeignKeyConstraint(
                $this->storageConfiguration->getTransactionTableName(),
                [$this->storageConfiguration->getTransactionIdColumnName()],
                ['id'],
                ['onDelete' => 'RESTRICT']
            );
        }
    }
}
