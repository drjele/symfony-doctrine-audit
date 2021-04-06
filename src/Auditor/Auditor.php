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
use Drjele\DoctrineAudit\Contract\UserProviderInterface;
use Drjele\DoctrineAudit\Service\AnnotationReadService;

final class Auditor implements EventSubscriber
{
    private array $entities;
    private EntityManagerInterface $entityManager;
    private StorageInterface $storage;
    private UserProviderInterface $userProvider;

    public function __construct(
        AnnotationReadService $annotationReadService,
        EntityManagerInterface $entityManager,
        StorageInterface $storage,
        UserProviderInterface $userProvider
    ) {
        $this->entities = $annotationReadService->read($entityManager);
        $this->entityManager = $entityManager;
        $this->storage = $storage;
        $this->userProvider = $userProvider;
    }

    public function getSubscribedEvents()
    {
        return [Events::postFlush];
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
    }
}
