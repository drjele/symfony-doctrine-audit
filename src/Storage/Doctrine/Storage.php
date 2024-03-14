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
use Drjele\Doctrine\Audit\Trait\ThrowTrait;
use Psr\Log\LoggerInterface;
use Throwable;

final class Storage implements StorageInterface
{
    use ThrowTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Configuration $configuration,
        private readonly ?LoggerInterface $logger,
    ) {}

    public function save(StorageDto $storageDto): void
    {
        if (true === empty($storageDto->getEntities())) {
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

        return (int)$connection->lastInsertId();
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

        try {
            $connection->executeStatement($sql, $values, $types);
        } catch (Throwable $t) {
            $this->throw($t, ['sql' => $sql]);
        }
    }
}
