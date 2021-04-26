<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Storage\Doctrine;

final class Config
{
    private string $transactionTableName;
    private string $transactionIdColumnName;
    private string $transactionIdColumnType;
    private string $operationColumnName;

    public function __construct(array $config)
    {
        /* @todo maybe validate keys */
        $this->transactionTableName = $config['transaction_table_name'] ?? 'audit_transaction';
        $this->transactionIdColumnName = $config['transaction_id_column_name'] ?? 'audit_transaction_id';
        $this->transactionIdColumnType = $config['transaction_id_column_type'] ?? 'integer';
        $this->operationColumnName = $config['operation_column_name'] ?? 'audit_operation';
    }

    public function getTransactionTableName(): ?string
    {
        return $this->transactionTableName;
    }

    public function getTransactionIdColumnName(): ?string
    {
        return $this->transactionIdColumnName;
    }

    public function getTransactionIdColumnType(): ?string
    {
        return $this->transactionIdColumnType;
    }

    public function getOperationColumnName(): ?string
    {
        return $this->operationColumnName;
    }
}
