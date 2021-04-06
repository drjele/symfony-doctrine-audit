<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Contract;

use Drjele\DoctrineAudit\Dto\Storage\StorageDto;

interface StorageInterface
{
    public function save(StorageDto $storageDto): void;
}
