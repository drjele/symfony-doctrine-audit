<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Contract;

use Drjele\DoctrineAudit\Dto\Storage\TransactionDto;

interface TransactionProviderInterface
{
    public function getTransaction(): TransactionDto;
}
