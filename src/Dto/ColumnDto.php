<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto;

class ColumnDto
{
    private string $name;
    private $value;
    private string $type;

    public function __construct(string $name, $value, string $type)
    {
        $this->name = $name;
        $this->value = $value;
        $this->type = $type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getType(): ?string
    {
        return $this->type;
    }
}
