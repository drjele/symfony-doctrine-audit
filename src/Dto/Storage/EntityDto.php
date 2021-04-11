<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Storage;

use Drjele\DoctrineAudit\Exception\Exception;

class EntityDto
{
    use EntityDtoTrait;

    public const OPERATION_DELETE = 'delete';
    public const OPERATION_INSERT = 'insert';
    public const OPERATION_UPDATE = 'update';

    public const OPERATIONS = [
        self::OPERATION_DELETE,
        self::OPERATION_INSERT,
        self::OPERATION_UPDATE,
    ];

    public function __construct(string $operation, string $class, array $columns)
    {
        if (!\in_array($operation, static::OPERATIONS, true)) {
            throw new Exception(\sprintf('invalid audit operation "%s"', $operation));
        }

        $this->operation = $operation;
        $this->class = $class;
        $this->columns = $columns;
    }
}
