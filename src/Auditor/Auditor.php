<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Auditor;

use Doctrine\ORM\EntityManagerInterface;
use Drjele\DoctrineAudit\Contract\StorageInterface;

final class Auditor
{
    private EntityManagerInterface $entityManager;
    private array $entites;
    private StorageInterface $storage;

    public function __construct(EntityManagerInterface $entityManager, array $entites, StorageInterface $storage)
    {
        $this->entityManager = $entityManager;
        $this->entites = $entites;
        $this->storage = $storage;
    }
}
