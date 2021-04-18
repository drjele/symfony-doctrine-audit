<?php

/*
 * Copyright (c) 2018-2020 Multinode Network Srl. All rights reserved.
 * This source file is subject to the LICENSE bundled with this source code part of MNN CoreAPI.
 *
 * (authors) TDD SD Team
 */

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

class AnnotationReadService
{
    private AnnotationReader $reader;

    public function __construct()
    {
        $this->reader = new AnnotationReader();
    }

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

        $ignoredColumns = [];

        foreach ($reflection->getProperties() as $property) {
            /* @todo add global ignore columns config */

            if ($this->reader->getPropertyAnnotation($property, Ignore::class)) {
                $ignoredColumns[] = $property->getName();
            }
        }

        return new EntityDto($metadata->getName(), $ignoredColumns);
    }
}
