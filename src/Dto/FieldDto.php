<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Dto;

final class FieldDto
{
    public function __construct(
        private readonly string $name,
        private readonly string $columnName,
        private readonly string $type,
        private readonly mixed $value
    ) {}

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getColumnName(): ?string
    {
        return $this->columnName;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
