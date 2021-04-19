<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Auditor;

use Drjele\DoctrineAudit\Dto\AbstractEntityDto;
use Drjele\DoctrineAudit\Dto\FieldDto;
use Drjele\DoctrineAudit\Dto\Storage\EntityDto as StorageEntityDto;
use Drjele\DoctrineAudit\Exception\Exception;

class EntityDto extends AbstractEntityDto
{
    public function __construct(string $operation, string $class, string $tableName)
    {
        if (!\in_array($operation, StorageEntityDto::OPERATIONS, true)) {
            throw new Exception(\sprintf('invalid audit operation "%s"', $operation));
        }

        $this->operation = $operation;
        $this->class = $class;
        $this->tableName = $tableName;
        $this->fields = [];
    }

    public function addField(FieldDto $fieldDto): self
    {
        $this->fields[] = $fieldDto;

        return $this;
    }
}
