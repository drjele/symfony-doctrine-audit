<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Contract;

use Drjele\DoctrineAudit\Dto\Revision\RevisionDto;

interface StorageInterface
{
    public function save(RevisionDto $revisionDto): void;
}
