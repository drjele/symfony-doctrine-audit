<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Storage;

use Drjele\DoctrineAudit\Dto\ColumnDto;

trait EntityDtoTrait
{
    private string $operation;
    private string $class;
    private array $columns;

    public function getOperation(): ?string
    {
        return $this->operation;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    /** @return ColumnDto[] */
    public function getColumns(): ?array
    {
        return $this->columns;
    }
}
