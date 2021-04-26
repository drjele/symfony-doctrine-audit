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
use Drjele\DoctrineAudit\Auditor\Config as AuditorConfig;
use Drjele\DoctrineAudit\Dto\Storage\EntityDto;
use Drjele\DoctrineAudit\Exception\Exception;
use Drjele\DoctrineAudit\Service\AnnotationReadService;
use Drjele\DoctrineAudit\Storage\Doctrine\Config as StorageConfig;

class DoctrineSchemaSubscriber implements EventSubscriber
{
    private AnnotationReadService $annotationReadService;
    private AuditorConfig $auditorConfig;
    private StorageConfig $storageConfig;

    public function __construct(
        AnnotationReadService $annotationReadService,
        AuditorConfig $auditorConfig,
        StorageConfig $storageConfig
    ) {
        $this->annotationReadService = $annotationReadService;
        $this->auditorConfig = $auditorConfig;
        $this->storageConfig = $storageConfig;
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
            if (\in_array($field, $entityDto->getIgnoredFields())) {
                continue;
            }

            if (\in_array($field, $this->auditorConfig->getIgnoredFields())) {
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

        if (0 == $auditedColums) {
            return;
        }

        $auditTable->addColumn(
            $this->storageConfig->getTransactionIdColumnName(),
            $this->storageConfig->getTransactionIdColumnType()
        );
        $auditTable->addColumn(
            'audit_operation',
            'string',
            [
                'columnDefinition' => \sprintf('ENUM("%s", "%s", "%s")', ...EntityDto::OPERATIONS),
            ]
        );

        $primaryKeyColumns = $entityTable->getPrimaryKey()->getColumns();
        $primaryKeyColumns[] = $this->storageConfig->getTransactionIdColumnName();
        $auditTable->setPrimaryKey($primaryKeyColumns);

        $auditTable->addIndex([$this->storageConfig->getTransactionIdColumnName()], $this->storageConfig->getTransactionIdColumnName());
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        $schema = $eventArgs->getSchema();

        $transactionTable = $schema->createTable(
            $this->storageConfig->getTransactionTableName()
        );

        $transactionTable->addColumn(
            'id',
            $this->storageConfig->getTransactionIdColumnType(),
            [
                'autoincrement' => true,
            ]
        );
        $transactionTable->addColumn('username', 'string')->setNotnull(false);
        $transactionTable->addColumn('created', 'datetime');

        $transactionTable->setPrimaryKey(['id']);

        foreach ($schema->getTables() as $table) {
            if ($table->getName() == $this->storageConfig->getTransactionTableName()) {
                continue;
            }

            $table->addForeignKeyConstraint(
                $this->storageConfig->getTransactionTableName(),
                [$this->storageConfig->getTransactionIdColumnName()],
                ['id'],
                ['onDelete' => 'RESTRICT']
            );
        }
    }
}
