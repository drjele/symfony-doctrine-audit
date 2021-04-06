<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Revision;

class EntityDto
{
    use EntityDtoTrait;

    public const OPERATION_DELETE = 'delete';
    public const OPERATION_INSERT = 'insert';
    public const OPERATION_UPDATE = 'update';

    public function __construct(string $operation, string $class, array $columns)
    {
        $this->operation = $operation;
        $this->class = $class;
        $this->columns = $columns;
    }
}
