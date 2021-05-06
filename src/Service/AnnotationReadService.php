<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Service;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Drjele\DoctrineAudit\Annotation\Auditable;
use Drjele\DoctrineAudit\Annotation\Ignore;
use Drjele\DoctrineAudit\Dto\Annotation\EntityDto;
use Drjele\DoctrineAudit\Exception\Exception;

final class AnnotationReadService
{
    private AnnotationReader $reader;

    public function __construct()
    {
        $this->reader = new AnnotationReader();
    }

    /** @return EntityDto[] */
    public function read(EntityManagerInterface $entityManager): array
    {
        $entities = [];

        $metadatas = $entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($metadatas as $metadata) {
            $entityDto = $this->buildEntityDto($metadata);

            if (null === $entityDto) {
                continue;
            }

            $entityClass = $entityDto->getClass();

            if (isset($entities[$entityClass])) {
                throw new Exception(
                    \sprintf('duplicate annotation for entity class "%s"', $entityClass)
                );
            }

            $entities[$entityClass] = $entityDto;
        }

        return $entities;
    }

    public function buildEntityDto(ClassMetadata $metadata): ?EntityDto
    {
        $reflection = $metadata->getReflectionClass();

        $annotation = $this->reader->getClassAnnotation($reflection, Entity::class);
        if (null === $annotation) {
            /* ignore non entity */
            return null;
        }

        /** @var Auditable $auditableAnnotation */
        $auditableAnnotation = $this->reader->getClassAnnotation($reflection, Auditable::class);
        if (null === $auditableAnnotation) {
            /* ignore not auditable entity */
            return null;
        }

        if (false === $auditableAnnotation->enabled) {
            return null;
        }

        $ignoredFields = [];

        foreach ($reflection->getProperties() as $property) {
            /* @todo add global ignore columns config */

            if (false == $this->reader->getPropertyAnnotation($property, Ignore::class)) {
                continue;
            }

            $field = $property->getName();

            if ($metadata->isIdentifier($field)) {
                /* identifiers are never ignored */
                continue;
            }

            $ignoredFields[] = $field;
        }

        return new EntityDto($metadata->getName(), $ignoredFields);
    }
}
