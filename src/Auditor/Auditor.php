<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Auditor;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Drjele\DoctrineAudit\Contract\StorageInterface;
use Drjele\DoctrineAudit\Contract\UserProviderInterface;
use Drjele\DoctrineAudit\Dto\Revision\EntityDto;
use Drjele\DoctrineAudit\Dto\Revision\RevisionDto;
use Drjele\DoctrineAudit\Service\AnnotationReadService;

final class Auditor implements EventSubscriber
{
    private array $entities;
    private EntityManagerInterface $entityManager;
    private StorageInterface $storage;
    private UserProviderInterface $userProvider;

    /** processing data */
    private ?RevisionDto $revisionDto = null;

    public function __construct(
        AnnotationReadService $annotationReadService,
        EntityManagerInterface $entityManager,
        StorageInterface $storage,
        UserProviderInterface $userProvider
    ) {
        /* @todo read and set entities on cache create */
        $this->entities = $annotationReadService->read($entityManager);
        $this->entityManager = $entityManager;
        $this->storage = $storage;
        $this->userProvider = $userProvider;
    }

    public function getSubscribedEvents()
    {
        return [Events::onFlush, Events::postFlush];
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $deletions = $this->getScheduledEntityDeletions();

        $insertions = $this->getScheduledEntityInsertions();

        $updates = $this->getScheduledEntityUpdates();

        if (!$deletions && !$insertions && !$updates) {
            return;
        }

        $userDto = $this->userProvider->getUser();

        $this->revisionDto = new RevisionDto($userDto, $deletions, $insertions, $updates);
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
        if (null === $this->revisionDto) {
            return;
        }

        foreach ($this->revisionDto->getInsertions() as $insertion) {
            $data = $this->getOriginalEntityData($insertion->getEntity());
            \print_r($data);
        }
        exit;
        $this->storage->save($this->revisionDto);

        /* reset auditor state */
        $this->revisionDto = null;
    }

    private function getScheduledEntityDeletions(): array
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $entities = [];

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            $hash = \spl_object_hash($entity);

            if (isset($entities[$hash])) {
                continue;
            }

            $class = $this->entityManager->getClassMetadata(\get_class($entity));
            if (!$this->isAudited($class->name)) {
                continue;
            }

            $entities[$hash] = new EntityDto($entity);
        }

        return \array_values($entities);
    }

    private function getScheduledEntityInsertions(): array
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $entities = [];

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if (!$this->isAudited(\get_class($entity))) {
                continue;
            }

            $entities[\spl_object_hash($entity)] = new EntityDto($entity);
        }

        return \array_values($entities);
    }

    private function getScheduledEntityUpdates(): array
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $entities = [];

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$this->isAudited(\get_class($entity))) {
                continue;
            }

            $entities[\spl_object_hash($entity)] = new EntityDto($entity);
        }

        return \array_values($entities);
    }

    private function isAudited(string $entityClass): bool
    {
        return isset($this->entities[$entityClass]);
    }

    private function getOriginalEntityData($entity): array
    {
        $class = $this->entityManager->getClassMetadata(\get_class($entity));
        $data = $this->entityManager->getUnitOfWork()->getOriginalEntityData($entity);

        if ($class->isVersioned) {
            $versionField = $class->versionField;
            $data[$versionField] = $class->reflFields[$versionField]->getValue($entity);
        }

        return $data;
    }
}
