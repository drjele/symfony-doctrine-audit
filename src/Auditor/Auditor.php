<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Auditor;

use Doctrine\ORM\EntityManagerInterface;
use Drjele\DoctrineAudit\Contract\StorageInterface;
use Drjele\DoctrineAudit\Service\AnnotationService;

final class Auditor
{
    private EntityManagerInterface $entityManager;
    private StorageInterface $storage;
    private array $entites;

    public function __construct(
        AnnotationService $annotationService,
        EntityManagerInterface $entityManager,
        StorageInterface $storage
    ) {
        $this->entityManager = $entityManager;
        $this->storage = $storage;

        /* @todo do not init on constructor */
        $this->entites = $annotationService->read($entityManager);
    }
}
