<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto;

abstract class AbstractEntityDto
{
    public const OPERATION_DELETE = 'delete';
    public const OPERATION_INSERT = 'insert';
    public const OPERATION_UPDATE = 'update';

    public const OPERATIONS = [
        self::OPERATION_DELETE,
        self::OPERATION_INSERT,
        self::OPERATION_UPDATE,
    ];

    protected string $operation;
    protected string $class;
    protected string $tableName;
    protected array $columns;

    public function getOperation(): ?string
    {
        return $this->operation;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    /** @return ColumnDto[] */
    public function getColumns(): ?array
    {
        return $this->columns;
    }
}
