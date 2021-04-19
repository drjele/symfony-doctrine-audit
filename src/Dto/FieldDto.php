<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto;

class FieldDto
{
    private string $name;
    private string $columnName;
    private string $type;
    private $value;

    public function __construct(string $name, string $columnName, string $type, $value)
    {
        $this->name = $name;
        $this->columnName = $columnName;
        $this->type = $type;
        $this->value = $value;
    }

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

    public function getValue()
    {
        return $this->value;
    }
}
