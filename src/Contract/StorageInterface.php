<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Contract;

use Drjele\Doctrine\Audit\Dto\Storage\StorageDto;

interface StorageInterface
{
    public function save(StorageDto $storageDto): void;
}
