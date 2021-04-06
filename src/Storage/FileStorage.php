<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Storage;

use Drjele\DoctrineAudit\Contract\StorageInterface;
use Drjele\DoctrineAudit\Dto\Revision\RevisionDto;

class FileStorage implements StorageInterface
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function save(RevisionDto $revisionDto): void
    {
    }
}
