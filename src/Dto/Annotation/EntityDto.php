<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Annotation;

class EntityDto
{
    private string $class;
    private array $ignoredColumns;

    public function __construct(string $class, array $ignoredColumns)
    {
        $this->class = $class;
        $this->ignoredColumns = $ignoredColumns;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getIgnoredColumns(): ?array
    {
        return $this->ignoredColumns;
    }
}
