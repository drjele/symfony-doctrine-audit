<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Type;

use Drjele\Doctrine\Audit\Dto\Auditor\EntityDto;
use Drjele\Doctrine\Type\Contract\AbstractEnumType;

class AuditOperationType extends AbstractEnumType
{
    public static function getTypeName(): string
    {
        return (new self())->getName();
    }

    public function getValues(): array
    {
        return EntityDto::OPERATIONS;
    }
}
