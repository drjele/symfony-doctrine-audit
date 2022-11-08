<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Storage\Doctrine;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Drjele\Doctrine\Audit\Contract\StorageInterface;
use Drjele\Doctrine\Audit\Dto\Storage\EntityDto;
use Drjele\Doctrine\Audit\Dto\Storage\StorageDto;
use Drjele\Doctrine\Audit\Dto\Storage\TransactionDto;

final class Storage implements StorageInterface
{
    private EntityManagerInterface $entityManager;
    private Configuration $configuration;

    public function __construct(
        EntityManagerInterface $entityManager,
        Configuration $configuration
    ) {
        $this->entityManager = $entityManager;
        $this->configuration = $configuration;
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

    private function getTransactionId(TransactionDto $transactionDto): int
    {
        $connection = $this->entityManager->getConnection();

        $connection->insert(
            $this->configuration->getTransactionTableName(),
            [
                'username' => $transactionDto->getUsername(),
                'created' => new DateTime(),
            ],
            [
                Types::STRING,
                Types::DATE_MUTABLE,
            ]
        );

        $platform = $connection->getDatabasePlatform();

        $sequenceName = $platform->supportsSequences()
            ? $platform->getIdentitySequenceName($this->configuration->getTransactionIdColumnType(), 'id')
            : null;

        return (int)$connection->lastInsertId($sequenceName);
    }

    private function saveEntity(int $transactionId, EntityDto $entityDto): void
    {
        $columns = [
            $this->configuration->getTransactionIdColumnName(),
            $this->configuration->getOperationColumnName(),
        ];
        $values = [$transactionId, $entityDto->getOperation()];
        $types = [$this->configuration->getTransactionIdColumnType(), Types::STRING];

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
