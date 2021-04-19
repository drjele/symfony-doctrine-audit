<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Storage;

use Drjele\DoctrineAudit\Dto\AbstractEntityDto;
use Drjele\DoctrineAudit\Exception\Exception;

class EntityDto extends AbstractEntityDto
{
    public function __construct(string $operation, string $class, string $tableName, array $fields)
    {
        if (!\in_array($operation, static::OPERATIONS, true)) {
            throw new Exception(\sprintf('invalid audit operation "%s"', $operation));
        }

        $this->operation = $operation;
        $this->class = $class;
        $this->tableName = $tableName;
        $this->fields = $fields;
    }
}
