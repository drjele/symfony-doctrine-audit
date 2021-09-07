<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Dto\Storage;

use Drjele\Doctrine\Audit\Dto\AbstractEntityDto;
use Drjele\Doctrine\Audit\Exception\Exception;

final class EntityDto extends AbstractEntityDto
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
