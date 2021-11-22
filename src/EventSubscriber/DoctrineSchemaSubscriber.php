<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Drjele\Doctrine\Audit\Auditor\Configuration as AuditorConfiguration;
use Drjele\Doctrine\Audit\Exception\Exception;
use Drjele\Doctrine\Audit\Service\AnnotationReadService;
use Drjele\Doctrine\Audit\Storage\Doctrine\Configuration as StorageConfiguration;
use Drjele\Doctrine\Audit\Type\OperationType;
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

        Type::addType(OperationType::getDefaultName(), OperationType::class);
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

                try {
                    $field = $classMetadata->getFieldForColumn($columnName);
                    if (\in_array($field, $entityDto->getIgnoredFields(), true)) {
                        continue;
                    }

                    if (\in_array($field, $this->auditorConfiguration->getIgnoredFields(), true)) {
                        continue;
                    }

                    $options = \array_merge(
                        $column->getPlatformOptions(),
                        [
                            'notnull' => false,
                            'autoincrement' => false,
                        ]
                    );
                    unset($options['name'], $options['version']);

                    /* @var Column $column */
                    $auditTable->addColumn(
                        $columnName,
                        $column->getType()->getName(),
                        $options
                    );

                    ++$auditedColums;
                } catch (Throwable $t) {
                    throw new Exception(
                        \sprintf('`%s` => `%s`', $columnName, $t->getMessage()),
                        $t->getCode(),
                        $t
                    );
                }
            }

            if (0 === $auditedColums) {
                return;
            }

            $auditTable->addColumn(
                $this->storageConfiguration->getTransactionIdColumnName(),
                $this->storageConfiguration->getTransactionIdColumnType()
            );
            $auditTable->addColumn(
                $this->storageConfiguration->getOperationColumnName(),
                OperationType::getDefaultName(),
                ['notnull' => true]
            );

            $primaryKeyColumns = $entityTable->getPrimaryKey()->getColumns();
            $primaryKeyColumns[] = $this->storageConfiguration->getTransactionIdColumnName();
            $auditTable->setPrimaryKey($primaryKeyColumns);

            $auditTable->addIndex(
                [$this->storageConfiguration->getTransactionIdColumnName()],
                $this->storageConfiguration->getTransactionIdColumnName()
            );
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
