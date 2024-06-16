<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\EventSubscriber;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Drjele\Doctrine\Audit\Auditor\Configuration as AuditorConfiguration;
use Drjele\Doctrine\Audit\Exception\Exception;
use Drjele\Doctrine\Audit\Service\AnnotationReadService;
use Drjele\Doctrine\Audit\Storage\Doctrine\Configuration as StorageConfiguration;
use Drjele\Doctrine\Audit\Type\AuditOperationType;
use Drjele\Doctrine\Type\Contract\AbstractEnumType;
use Drjele\Doctrine\Type\Contract\AbstractSetType;
use Throwable;

final class DoctrineSchemaListener
{
    public function __construct(
        private readonly AnnotationReadService $annotationReadService,
        private readonly AuditorConfiguration $auditorConfiguration,
        private readonly StorageConfiguration $storageConfiguration
    ) {}

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

                    $column->setAutoincrement(false)
                        ->setNotnull(false);

                    $this->updateType($column);
                }

                if (true === empty($entityTable->getColumns())) {
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

                foreach ($table->getForeignKeys() as $foreignKey) {
                    $table->removeForeignKey($foreignKey->getName());
                }

                $table->dropPrimaryKey();
                foreach ($table->getIndexes() as $index) {
                    $table->dropIndex($index->getName());
                }

                foreach ($table->getUniqueConstraints() as $uniqueConstraint) {
                    $table->removeUniqueConstraint($uniqueConstraint->getName());
                }

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
            $transactionTable->addColumn('username', Types::STRING, ['length' => 500])->setNotnull(false);
            $transactionTable->addColumn('created', Types::DATETIME_IMMUTABLE);

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
        } catch (Throwable $t) {
            throw new Exception(
                \sprintf('`%s` => `%s`', $this->storageConfiguration->getTransactionTableName(), $t->getMessage()),
                $t->getCode(),
                $t
            );
        }
    }

    private function updateType(Column $column)
    {
        $columnType = $column->getType();

        /** @todo maybe keep the original type and only add more values to the set */

        switch (true) {
            case $columnType instanceof AbstractEnumType:
                $column->setType(Type::getType(Types::STRING))->setLength(255);
                break;
            case $columnType instanceof AbstractSetType:
                $column->setType(Type::getType(Types::STRING))->setLength(255);
                break;
            default:
                /** @todo handle other custom types */
                break;
        }
    }
}
