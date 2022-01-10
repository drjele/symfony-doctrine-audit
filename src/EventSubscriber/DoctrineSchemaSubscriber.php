<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Drjele\Doctrine\Audit\Auditor\Configuration as AuditorConfiguration;
use Drjele\Doctrine\Audit\Exception\Exception;
use Drjele\Doctrine\Audit\Service\AnnotationReadService;
use Drjele\Doctrine\Audit\Storage\Doctrine\Configuration as StorageConfiguration;
use Drjele\Doctrine\Audit\Type\AuditOperationType;
use Throwable;

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

        try {
            $auditable = false;

            try {
                $entityDto = $this->annotationReadService->buildEntityDto($classMetadata);
                if (null === $entityDto) {
                    return;
                }

                $table = $schema->getTable($entityTable->getName());

                foreach ($entityTable->getColumns() as $column) {
                    $columnName = $column->getName();

                    $field = $classMetadata->getFieldForColumn($columnName);
                    if (\in_array($field, $entityDto->getIgnoredFields(), true)
                        || \in_array($field, $this->auditorConfiguration->getIgnoredFields(), true)
                    ) {
                        $table->dropColumn($columnName);
                        continue;
                    }

                    $column->setAutoincrement(false);
                }

                if (!$entityTable->getColumns()) {
                    return;
                }

                $auditable = true;

                $table->addColumn(
                    $this->storageConfiguration->getTransactionIdColumnName(),
                    $this->storageConfiguration->getTransactionIdColumnType()
                );
                $table->addColumn(
                    $this->storageConfiguration->getOperationColumnName(),
                    AuditOperationType::getTypeName(),
                    ['notnull' => true]
                );

                $primaryKeyColumns = $entityTable->getPrimaryKey()->getColumns();
                $primaryKeyColumns[] = $this->storageConfiguration->getTransactionIdColumnName();
                $entityTable->dropPrimaryKey();
                $table->setPrimaryKey($primaryKeyColumns);

                $table->addIndex(
                    [$this->storageConfiguration->getTransactionIdColumnName()],
                    $this->storageConfiguration->getTransactionIdColumnName()
                );
            } finally {
                if (false === $auditable) {
                    $schema->dropTable($entityTable->getName());
                }
            }
        } catch (Throwable $t) {
            throw new Exception(
                \sprintf('`%s` => `%s`', $entityTable->getName(), $t->getMessage()),
                $t->getCode(),
                $t
            );
        }
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        try {
            $schema = $eventArgs->getSchema();

            $transactionTable = $schema->createTable(
                $this->storageConfiguration->getTransactionTableName()
            );

            $transactionTable->addColumn(
                'id',
                $this->storageConfiguration->getTransactionIdColumnType(),
                [
                    'autoincrement' => true,
                    'notnull' => true,
                ]
            );
            $transactionTable->addColumn('username', 'string')->setNotnull(false);
            $transactionTable->addColumn('created', 'datetime');

            $transactionTable->setPrimaryKey(['id']);

            foreach ($schema->getTables() as $table) {
                if ($table->getName() === $this->storageConfiguration->getTransactionTableName()) {
                    continue;
                }

                foreach ($table->getForeignKeys() as $foreignKey) {
                    $table->removeForeignKey($foreignKey->getName());
                }

                foreach ($table->getIndexes() as $index) {
                    $table->dropIndex($index->getName());
                }

                foreach ($table->getUniqueConstraints() as $uniqueConstraint) {
                    $table->removeUniqueConstraint($uniqueConstraint->getName());
                }

                $table->addForeignKeyConstraint(
                    $this->storageConfiguration->getTransactionTableName(),
                    [$this->storageConfiguration->getTransactionIdColumnName()],
                    ['id'],
                    ['onDelete' => 'RESTRICT']
                );
            }
        } catch (Throwable $t) {
            throw new Exception(
                \sprintf('`%s` => `%s`', $this->storageConfiguration->getTransactionTableName(), $t->getMessage()),
                $t->getCode(),
                $t
            );
        }
    }
}
