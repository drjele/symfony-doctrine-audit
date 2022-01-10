<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit;

use Doctrine\DBAL\Types\Type;
use Drjele\Doctrine\Audit\Type\AuditOperationType;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DrjeleDoctrineAuditBundle extends Bundle
{
    public function boot(): void
    {
        parent::boot();

        Type::addType(AuditOperationType::getTypeName(), AuditOperationType::class);
    }
}
