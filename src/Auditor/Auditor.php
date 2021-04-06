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
use Drjele\DoctrineAudit\Dto\Auditor\AuditorDto;
use Drjele\DoctrineAudit\Dto\Auditor\EntityDto as AuditorEntityDto;
use Drjele\DoctrineAudit\Dto\Revision\EntityDto;
use Drjele\DoctrineAudit\Dto\Revision\RevisionDto;
use Drjele\DoctrineAudit\Exception\Exception;
use Drjele\DoctrineAudit\Service\AnnotationReadService;
use Psr\Log\LoggerInterface;
use Throwable;

final class Auditor implements EventSubscriber
{
    private array $auditedEntities;
    private EntityManagerInterface $entityManager;
    private StorageInterface $storage;
    private UserProviderInterface $userProvider;
    private ?LoggerInterface $logger;

    /** processing data */
    private ?AuditorDto $auditorDto = null;

    public function __construct(
        AnnotationReadService $annotationReadService,
        EntityManagerInterface $entityManager,
        StorageInterface $storage,
        UserProviderInterface $userProvider,
        ?LoggerInterface $logger
    ) {
        /* @todo read and set entities on cache create */
        $this->auditedEntities = $annotationReadService->read($entityManager);
        $this->entityManager = $entityManager;
        $this->storage = $storage;
        $this->userProvider = $userProvider;
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return [Events::onFlush, Events::postFlush];
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        /* @todo compute updates revisions somewhere */

        try {
            $unitOfWork = $this->entityManager->getUnitOfWork();

            /** @todo compute deletions revisions here */
            $entitiesToDelete = $this->filterAuditedEntities($unitOfWork->getScheduledEntityDeletions());

            $entitiesToInsert = $this->filterAuditedEntities($unitOfWork->getScheduledEntityInsertions());

            $entitiesToUpdate = $this->filterAuditedEntities($unitOfWork->getScheduledEntityUpdates());

            if (!$entitiesToDelete && !$entitiesToInsert && !$entitiesToUpdate) {
                return;
            }

            $this->auditorDto = new AuditorDto($entitiesToDelete, $entitiesToInsert, $entitiesToUpdate);
        } catch (Throwable $t) {
            $this->handleThrowable($t);
        }
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
        try {
            if (null === $this->auditorDto) {
                return;
            }

            /** @todo compute insertions revisions here */
            $revisonDto = $this->createRevisonDto();

            $this->storage->save($revisonDto);

            /* reset auditor state */
            $this->auditorDto = null;
        } catch (Throwable $t) {
            $this->handleThrowable($t);
        }
    }

    private function createRevisonDto(): RevisionDto
    {
        $user = $this->userProvider->getUser();

        $entities = \array_map(
            function (AuditorEntityDto $revisionDto) {
                return new EntityDto(
                    $revisionDto->getOperation(),
                    $revisionDto->getClass(),
                    $revisionDto->getColumns()
                );
            },
            $this->auditorDto->getRevisions()
        );

        return new RevisionDto($user, $entities);
    }

    private function filterAuditedEntities(array $allEntities)
    {
        $entities = [];

        foreach ($allEntities as $entity) {
            $hash = \spl_object_hash($entity);

            if (isset($entities[$hash])) {
                continue;
            }

            if (!$this->isAudited(\get_class($entity))) {
                continue;
            }

            $entities[$hash] = $entity;
        }

        return \array_values($entities);
    }

    private function isAudited(string $entityClass): bool
    {
        return isset($this->auditedEntities[$entityClass]);
    }

    private function handleThrowable(Throwable $t): void
    {
        if ($this->logger) {
            $this->logger->error(
                $t->getMessage(),
                [
                    'code' => $t->getCode(),
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                    'trace' => $t->getTrace(),
                ]
            );
        }

        throw new Exception($t->getMessage(), $t->getCode(), $t);
    }
}
