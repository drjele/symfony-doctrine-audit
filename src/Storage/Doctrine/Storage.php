<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Storage\Doctrine;

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

class Storage implements StorageInterface, EventSubscriber
{
    private EntityManagerInterface $entityManager;
    private AnnotationReadService $annotationReadService;
    private Config $config;

    public function __construct(
        EntityManagerInterface $entityManager,
        AnnotationReadService $annotationReadService,
        array $config
    ) {
        $this->entityManager = $entityManager;
        $this->annotationReadService = $annotationReadService;
        $this->config = new Config($config);
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

        $auditTable->addColumn(
            $this->config->getTransactionIdColumnName(),
            $this->config->getTransactionIdColumnType()
        );
        $auditTable->addColumn(
            'audit_operation',
            'string',
            [
                'columnDefinition' => \sprintf('ENUM("%s", "%s", "%s")', ...EntityDto::OPERATIONS),
            ]
        );

        $primaryKeyColumns = $entityTable->getPrimaryKey()->getColumns();
        $primaryKeyColumns[] = $this->config->getTransactionIdColumnName();
        $auditTable->setPrimaryKey($primaryKeyColumns);

        $auditTable->addIndex([$this->config->getTransactionIdColumnName()], $this->config->getTransactionIdColumnName());
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        $schema = $eventArgs->getSchema();

        $transactionTable = $schema->createTable(
            $this->config->getTransactionTableName()
        );

        $transactionTable->addColumn(
            'id',
            $this->config->getTransactionIdColumnType(),
            [
                'autoincrement' => true,
            ]
        );
        $transactionTable->addColumn('username', 'string')->setNotnull(false);
        $transactionTable->addColumn('created', 'datetime');

        $transactionTable->setPrimaryKey(['id']);

        foreach ($schema->getTables() as $table) {
            if ($table->getName() == $this->config->getTransactionTableName()) {
                continue;
            }

            $table->addForeignKeyConstraint(
                $this->config->getTransactionTableName(),
                [$this->config->getTransactionIdColumnName()],
                ['id'],
                ['onDelete' => 'RESTRICT']
            );
        }
    }

    private function getTransactionId(TransactionDto $transactionDto): int
    {
        $connection = $this->entityManager->getConnection();

        $connection->insert(
            $this->config->getTransactionTableName(),
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
            ? $platform->getIdentitySequenceName($this->config->getTransactionIdColumnType(), 'id')
            : null;

        return (int)$connection->lastInsertId($sequenceName);
    }

    private function saveEntity(int $transactionId, EntityDto $entityDto): void
    {
        $columns = [
            $this->config->getTransactionIdColumnName(),
            $this->config->getOperationColumnName(),
        ];
        $values = [$transactionId, $entityDto->getOperation()];
        $types = [$this->config->getTransactionIdColumnType(), Types::STRING];

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
