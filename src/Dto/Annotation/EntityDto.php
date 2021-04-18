<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Annotation;

class EntityDto
{
    private string $class;
    private array $ignoredFields;

    public function __construct(string $class, array $ignoredFields)
    {
        $this->class = $class;
        $this->ignoredFields = $ignoredFields;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getIgnoredFields(): ?array
    {
        return $this->ignoredFields;
    }
}
