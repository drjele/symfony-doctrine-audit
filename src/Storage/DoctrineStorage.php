<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Storage;

use Doctrine\ORM\EntityManagerInterface;
use Drjele\DoctrineAudit\Contract\StorageInterface;
use Drjele\DoctrineAudit\Dto\Storage\StorageDto;
use Drjele\DoctrineAudit\Exception\Exception;

class DoctrineStorage implements StorageInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function save(StorageDto $storageDto): void
    {
        throw new Exception('implement');
    }
}
