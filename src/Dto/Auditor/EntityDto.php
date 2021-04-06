<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Auditor;

use Drjele\DoctrineAudit\Dto\ColumnDto;
use Drjele\DoctrineAudit\Dto\Storage\EntityDtoTrait;

class EntityDto
{
    use EntityDtoTrait;

    public function __construct(string $operation, string $class)
    {
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
