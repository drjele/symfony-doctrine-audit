<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto;

class ColumnDto
{
    private string $fieldName;
    private string $columnName;
    private string $type;
    private $value;

    public function __construct(string $fieldName, string $columnName, string $type, $value)
    {
        $this->fieldName = $fieldName;
        $this->columnName = $columnName;
        $this->type = $type;
        $this->value = $value;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
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
