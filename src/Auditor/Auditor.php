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
use Doctrine\ORM\Mapping\ClassMetadata;
use Drjele\DoctrineAudit\Contract\StorageInterface;
use Drjele\DoctrineAudit\Contract\TransactionProviderInterface;
use Drjele\DoctrineAudit\Dto\Annotation\EntityDto as AnnotationEntityDto;
use Drjele\DoctrineAudit\Dto\Auditor\AuditorDto;
use Drjele\DoctrineAudit\Dto\Auditor\EntityDto as AuditorEntityDto;
use Drjele\DoctrineAudit\Dto\FieldDto;
use Drjele\DoctrineAudit\Dto\Storage\EntityDto as StorageEntityDto;
use Drjele\DoctrineAudit\Dto\Storage\StorageDto;
use Drjele\DoctrineAudit\Exception\Exception;
use Drjele\DoctrineAudit\Service\AnnotationReadService;
use Psr\Log\LoggerInterface;
use Throwable;

final class Auditor implements EventSubscriber
{
    private Configuration $configuration;
    private EntityManagerInterface $entityManager;
    private StorageInterface $storage;
    private TransactionProviderInterface $transactionProvider;
    private ?LoggerInterface $logger;

    /** @var AnnotationReadService[] */
    private array $auditedEntities;
    private ?AuditorDto $auditorDto;

    public function __construct(
        Configuration $configuration,
        EntityManagerInterface $entityManager,
        StorageInterface $storage,
        TransactionProviderInterface $transactionProvider,
        ?LoggerInterface $logger,
        AnnotationReadService $annotationReadService
    ) {
        $this->configuration = $configuration;
        $this->entityManager = $entityManager;
        $this->storage = $storage;
        $this->transactionProvider = $transactionProvider;
        $this->logger = $logger;

        /* @todo read and set entities on cache create */
        $this->auditedEntities = $annotationReadService->read($entityManager);
        $this->auditorDto = null;
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

            $entitiesToDelete = $this->filterAuditedEntities($unitOfWork->getScheduledEntityDeletions());

            $entitiesToInsert = $this->filterAuditedEntities($unitOfWork->getScheduledEntityInsertions());

            $entitiesToUpdate = $this->filterAuditedEntities($unitOfWork->getScheduledEntityUpdates());

            if (!$entitiesToDelete && !$entitiesToInsert && !$entitiesToUpdate) {
                return;
            }

            $this->auditorDto = new AuditorDto($entitiesToDelete, $entitiesToInsert, $entitiesToUpdate);

            $this->createAuditEntities($entitiesToDelete, StorageEntityDto::OPERATION_DELETE);
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

            $this->createAuditEntities(
                $this->auditorDto->getEntitiesToInsert(),
                StorageEntityDto::OPERATION_INSERT
            );

            $this->createAuditEntities(
                $this->auditorDto->getEntitiesToUpdate(),
                StorageEntityDto::OPERATION_UPDATE
            );

            $storageDto = $this->createStorageDto();

            $this->storage->save($storageDto);
        } catch (Throwable $t) {
            $this->handleThrowable($t);
        } finally {
            /* reset auditor state */
            $this->auditorDto = null;

            \gc_collect_cycles();
        }
    }

    private function createAuditEntities(array $entities, string $operation): void
    {
        $auditor = $this->auditorDto;
        $unitOfWork = $this->entityManager->getUnitOfWork();

        foreach ($entities as $entity) {
            $entityData = \array_merge(
                $this->getOriginalEntityData($entity),
                $unitOfWork->getEntityIdentifier($entity)
            );

            $entityDtos = $this->createAuditorEntityDtos(
                $this->entityManager->getClassMetadata(\get_class($entity)),
                $entityData,
                $operation
            );

            foreach ($entityDtos as $entityDto) {
                $auditor->addAuditEntity($entityDto);
            }
        }
    }

    private function createAuditorEntityDtos(
        ClassMetadata $class,
        array $entityData,
        string $operation
    ): array {
        $entityDtos = [];

        $unitOfWork = $this->entityManager->getUnitOfWork();

        $auditorEntityDto = new AuditorEntityDto(
            $operation,
            $class->getName(),
            $this->getTableName($class)
        );

        $entityDtos[] = $auditorEntityDto;

        foreach ($class->getAssociationMappings() as $field => $association) {
            if ($class->isInheritanceTypeJoined() && $class->isInheritedAssociation($field)) {
                continue;
            }

            if (!(($association['type'] & ClassMetadata::TO_ONE) > 0 && $association['isOwningSide'])) {
                continue;
            }

            $data = $entityData[$field] ?? null;

            if (null !== $data && $unitOfWork->isInIdentityMap($data)) {
                $relatedId = $unitOfWork->getEntityIdentifier($data);
            }

            $targetClass = $this->entityManager->getClassMetadata($association['targetEntity']);

            foreach ($association['joinColumns'] as $joinColumn) {
                $sourceColumn = $this->getJoinColumnName($joinColumn, $class);

                /** @todo refactor to not use deprecated method */
                $targetColumn = $targetClass->getFieldForColumn($joinColumn['referencedColumnName']);
                $type = $targetClass->getTypeOfColumn($targetColumn);

                $value = $relatedId[$targetColumn] ?? null;

                $auditorEntityDto->addField(
                    new FieldDto($field, $sourceColumn, $type, $value)
                );
            }
        }

        foreach ($class->getFieldNames() as $field) {
            if (true == $class->isInheritanceTypeJoined()
                && true == $class->isInheritedField($field)
                && false == $class->isIdentifier($field)) {
                continue;
            }

            $columnName = $this->getColumnName($field, $class);
            $type = $class->getFieldMapping($field)['type'];
            $value = $entityData[$field] ?? null;

            $auditorEntityDto->addField(
                new FieldDto($field, $columnName, $type, $value)
            );
        }

        if ($class->isInheritanceTypeSingleTable()) {
            $auditorEntityDto->addField(
                new FieldDto(
                    $class->discriminatorColumn['fieldName'],
                    $class->discriminatorColumn['name'],
                    $class->discriminatorColumn['type'],
                    $class->discriminatorValue
                )
            );
        }

        if ($class->isInheritanceTypeJoined()) {
            $field = $class->discriminatorColumn['fieldName'];

            if (true === $class->isRootEntity()) {
                $auditorEntityDto->addField(
                    new FieldDto(
                        $field,
                        $class->discriminatorColumn['name'],
                        $class->discriminatorColumn['type'],
                        $entityData[$field] ?? null
                    )
                );
            } else {
                $entityData[$field] = $class->discriminatorValue;

                $entityDtos = \array_merge(
                    $this->createAuditorEntityDtos(
                        $this->entityManager->getClassMetadata($class->rootEntityName),
                        $entityData,
                        $operation
                    ),
                    $entityDtos
                );
            }
        }

        return $entityDtos;
    }

    private function getJoinColumnName(array $joinColumn, ClassMetadata $class): string
    {
        $quoteStrategy = $this->entityManager->getConfiguration()->getQuoteStrategy();
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();

        return $quoteStrategy->getJoinColumnName($joinColumn, $class, $platform);
    }

    private function getTableName(ClassMetadata $class): string
    {
        $quoteStrategy = $this->entityManager->getConfiguration()->getQuoteStrategy();
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();

        return $quoteStrategy->getTableName($class, $platform);
    }

    private function getColumnName(string $field, ClassMetadata $class): string
    {
        $quoteStrategy = $this->entityManager->getConfiguration()->getQuoteStrategy();
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();

        return $quoteStrategy->getColumnName($field, $class, $platform);
    }

    private function getOriginalEntityData($entity)
    {
        $class = $this->entityManager->getClassMetadata(\get_class($entity));
        $data = $this->entityManager->getUnitOfWork()->getOriginalEntityData($entity);

        if ($class->isVersioned) {
            $versionField = $class->versionField;
            $data[$versionField] = $class->reflFields[$versionField]->getValue($entity);
        }

        return $data;
    }

    private function createStorageDto(): StorageDto
    {
        $transaction = $this->transactionProvider->getTransaction();

        $entities = \array_map(
            function (AuditorEntityDto $entityDto): ?StorageEntityDto {
                $fields = [];
                /** @var AnnotationEntityDto $annotationEntityDto */
                $annotationEntityDto = $this->auditedEntities[$entityDto->getClass()];

                foreach ($entityDto->getFields() as $fieldDto) {
                    if (\in_array($fieldDto->getName(), $annotationEntityDto->getIgnoredFields())) {
                        continue;
                    }

                    if (\in_array($fieldDto->getName(), $this->configuration->getIgnoredFields())) {
                        continue;
                    }

                    $fields[] = $fieldDto;
                }

                if (!$fields) {
                    return null;
                }

                return new StorageEntityDto(
                    $entityDto->getOperation(),
                    $entityDto->getClass(),
                    $entityDto->getTableName(),
                    $fields
                );
            },
            $this->auditorDto->getAuditEntities()
        );

        return new StorageDto($transaction, \array_filter($entities));
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
