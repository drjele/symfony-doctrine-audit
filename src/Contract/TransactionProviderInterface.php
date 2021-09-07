<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Contract;

use Drjele\Doctrine\Audit\Dto\Storage\TransactionDto;

interface TransactionProviderInterface
{
    public function getTransaction(): TransactionDto;
}
