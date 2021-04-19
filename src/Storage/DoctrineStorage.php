<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Storage;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Drjele\DoctrineAudit\Contract\StorageInterface;
use Drjele\DoctrineAudit\Dto\Storage\EntityDto;
use Drjele\DoctrineAudit\Dto\Storage\StorageDto;
use Drjele\DoctrineAudit\Dto\Storage\TransactionDto;
use Drjele\DoctrineAudit\Exception\Exception;
use Drjele\DoctrineAudit\Service\AnnotationReadService;

class DoctrineStorage implements StorageInterface, EventSubscriber
{
    /** @todo move to config */
    private const AUDIT_TRANSACTION = 'audit_transaction';
    private const AUDIT_TRANSACTION_ID = 'audit_transaction_id';
    private const AUDIT_TRANSACTION_ID_TYPE = 'integer';
    private const AUDIT_OPERATION = 'audit_operation';

    private EntityManagerInterface $entityManager;
    private AnnotationReadService $annotationReadService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AnnotationReadService $annotationReadService
    ) {
        $this->entityManager = $entityManager;
        $this->annotationReadService = $annotationReadService;
    }

    public function getSubscribedEvents()
    {
        return [
            ToolEvents::postGenerateSchemaTable,
            ToolEvents::postGenerateSchema,
        ];
    }

    public function save(StorageDto $storageDto): void
    {
        if (!$storageDto->getEntities()) {
            return;
        }

        $transactionId = $this->getTransactionId($storageDto->getTransaction());

        foreach ($storageDto->getEntities() as $entity) {
            $this->saveEntity($transactionId, $entity);
        }
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

        $auditTable->addIndex([static::AUDIT_TRANSACTION_ID], static::AUDIT_TRANSACTION_ID);
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

        foreach ($schema->getTables() as $table) {
            if ($table->getName() == static::AUDIT_TRANSACTION) {
                continue;
            }

            $table->addForeignKeyConstraint(
                static::AUDIT_TRANSACTION,
                [static::AUDIT_TRANSACTION_ID],
                ['id'],
                ['onDelete' => 'RESTRICT']
            );
        }
    }

    private function getTransactionId(TransactionDto $transactionDto): int
    {
        $connection = $this->entityManager->getConnection();

        $connection->insert(
            static::AUDIT_TRANSACTION,
            [
                'username' => $transactionDto->getUsername(),
                'created' => new \DateTime(),
            ],
            [
                Types::STRING,
                Types::DATE_MUTABLE,
            ]
        );

        $platform = $connection->getDatabasePlatform();

        $sequenceName = $platform->supportsSequences()
            ? $platform->getIdentitySequenceName(static::AUDIT_TRANSACTION_ID_TYPE, 'id')
            : null;

        return (int)$connection->lastInsertId($sequenceName);
    }

    private function saveEntity(int $transactionId, EntityDto $entityDto): void
    {
        $columns = [static::AUDIT_TRANSACTION_ID, static::AUDIT_OPERATION];
        $values = [$transactionId, $entityDto->getOperation()];
        $types = [static::AUDIT_TRANSACTION_ID_TYPE, Types::STRING];

        foreach ($entityDto->getFields() as $columnDto) {
            $columns[] = $columnDto->getColumnName();
            $values[] = $columnDto->getValue();
            $types[] = $columnDto->getType();
        }

        $sql = \sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $entityDto->getTableName(),
            \implode(', ', $columns),
            \implode(', ', \array_fill(0, \count($columns), '?'))
        );

        $connection = $this->entityManager->getConnection();

        $connection->executeStatement($sql, $values, $types);
    }
}
