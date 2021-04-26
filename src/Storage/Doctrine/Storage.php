<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Storage\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Drjele\DoctrineAudit\Contract\StorageInterface;
use Drjele\DoctrineAudit\Dto\Storage\EntityDto;
use Drjele\DoctrineAudit\Dto\Storage\StorageDto;
use Drjele\DoctrineAudit\Dto\Storage\TransactionDto;

final class Storage implements StorageInterface
{
    private EntityManagerInterface $entityManager;
    private Config $config;

    public function __construct(
        EntityManagerInterface $entityManager,
        Config $config
    ) {
        $this->entityManager = $entityManager;
        $this->config = $config;
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