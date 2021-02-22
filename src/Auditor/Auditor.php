<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Auditor;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Drjele\DoctrineAudit\Contract\StorageInterface;
use Drjele\DoctrineAudit\Dto\AnnotationDto;
use Drjele\DoctrineAudit\Service\AnnotationService;

final class Auditor implements EventSubscriber
{
    private AnnotationService $annotationService;
    private EntityManagerInterface $entityManager;
    private StorageInterface $storage;

    public function __construct(
        AnnotationService $annotationService,
        EntityManagerInterface $entityManager,
        StorageInterface $storage
    ) {
        $this->annotationService = $annotationService;
        $this->entityManager = $entityManager;
        $this->storage = $storage;
    }

    public function getSubscribedEvents()
    {
        return [Events::postFlush];
    }

    public function postFlush(PostFlushEventArgs $postFlushEventArgs): void
    {
        $entityManager = $postFlushEventArgs->getEntityManager();

        /* @todo add simple check */
        if (\spl_object_hash($entityManager) != \spl_object_hash($this->entityManager)) {
            return;
        }

        $entities = $this->getEntities();

        $this->save($entities);
    }

    private function save(array $entities): void
    {
    }

    /** @return AnnotationDto[] */
    private function getEntities(): array
    {
        static $entities = null;

        if (null !== $entities) {
            $entities = $this->annotationService->read($this->entityManager);
        }

        return $entities;
    }
}
