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
use Drjele\DoctrineAudit\Contract\TransactionProviderInterface;
use Drjele\DoctrineAudit\Dto\Auditor\AuditorDto;
use Drjele\DoctrineAudit\Dto\Auditor\EntityDto as AuditorEntityDto;
use Drjele\DoctrineAudit\Dto\Storage\EntityDto;
use Drjele\DoctrineAudit\Dto\Storage\StorageDto;
use Drjele\DoctrineAudit\Exception\Exception;
use Drjele\DoctrineAudit\Service\AnnotationReadService;
use Psr\Log\LoggerInterface;
use Throwable;

final class Auditor implements EventSubscriber
{
    private array $auditedEntities;
    private EntityManagerInterface $entityManager;
    private StorageInterface $storage;
    private TransactionProviderInterface $transactionProvider;
    private ?LoggerInterface $logger;

    /** processing data */
    private ?AuditorDto $auditorDto = null;

    public function __construct(
        AnnotationReadService $annotationReadService,
        EntityManagerInterface $entityManager,
        StorageInterface $storage,
        TransactionProviderInterface $transactionProvider,
        ?LoggerInterface $logger
    ) {
        /* @todo read and set entities on cache create */
        $this->auditedEntities = $annotationReadService->read($entityManager);
        $this->entityManager = $entityManager;
        $this->storage = $storage;
        $this->transactionProvider = $transactionProvider;
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return [Events::onFlush, Events::postFlush];
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        /* @todo compute updates transactions somewhere */

        try {
            $unitOfWork = $this->entityManager->getUnitOfWork();

            /** @todo compute deletions transactions here */
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

            /** @todo compute insertions transactions here */
            $storageDto = $this->createStorageDto();

            $this->storage->save($storageDto);

            /* reset auditor state */
            $this->auditorDto = null;

            \gc_collect_cycles();
        } catch (Throwable $t) {
            $this->handleThrowable($t);
        }
    }

    private function createStorageDto(): StorageDto
    {
        $transaction = $this->transactionProvider->getTransaction();

        $entities = \array_map(
            function (AuditorEntityDto $transactionDto) {
                return new EntityDto(
                    $transactionDto->getOperation(),
                    $transactionDto->getClass(),
                    $transactionDto->getColumns()
                );
            },
            $this->auditorDto->getEntities()
        );

        return new StorageDto($transaction, $entities);
    }

    private function filterAuditedEntities(array $allEntities): array
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
