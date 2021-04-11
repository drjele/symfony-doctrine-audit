<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Auditor;

use Drjele\DoctrineAudit\Dto\ColumnDto;
use Drjele\DoctrineAudit\Dto\Storage\EntityDto as StorageEntityDto;
use Drjele\DoctrineAudit\Dto\Storage\EntityDtoTrait;
use Drjele\DoctrineAudit\Exception\Exception;

class EntityDto
{
    use EntityDtoTrait;

    public function __construct(string $operation, string $class)
    {
        if (!\in_array($operation, StorageEntityDto::OPERATIONS, true)) {
            throw new Exception(\sprintf('invalid audit operation "%s"', $operation));
        }

        $this->operation = $operation;
        $this->class = $class;
        $this->columns = [];
    }

    public function addColumn(ColumnDto $columnDto): self
    {
        $this->columns[] = $columnDto;

        return $this;
    }
}
