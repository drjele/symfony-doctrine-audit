<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Type;

use Drjele\Doctrine\Audit\Dto\Auditor\EntityDto;
use Drjele\Doctrine\Type\Contract\AbstractEnumType;

class OperationType extends AbstractEnumType
{
    public function getValues(): array
    {
        return EntityDto::OPERATIONS;
    }
}
