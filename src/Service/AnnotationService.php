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
use Drjele\DoctrineAudit\Dto\AnnotationDto;
use Drjele\DoctrineAudit\Exception\Exception;

class AnnotationService
{
    private AnnotationReader $reader;

    public function __construct()
    {
        $this->reader = new AnnotationReader();
    }

    public function read(EntityManagerInterface $entityManager): array
    {
        $annotations = [];

        $metadatas = $entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($metadatas as $metadata) {
            $annotationDto = $this->buildAnnotationDto($metadata);

            if (null === $annotationDto) {
                continue;
            }

            $class = $metadata->getName();

            if (isset($annotations[$class])) {
                throw new Exception(
                    \sprintf('duplicate annotation for class "%s"', $class)
                );
            }

            $annotations[$metadata->getName()] = $annotationDto;
        }

        return $annotations;
    }

    private function buildAnnotationDto(ClassMetadata $metadata): ?AnnotationDto
    {
        $reflection = $metadata->getReflectionClass();

        $annotation = $this->reader->getClassAnnotation($reflection, Entity::class);
        if (null === $annotation) {
            return null;
        }

        /** @var ?Auditable $auditableAnnotation */
        $auditableAnnotation = $this->reader->getClassAnnotation($reflection, Auditable::class);
        if (null === $auditableAnnotation) {
            return null;
        }

        $ignoredColumns = [];

        foreach ($reflection->getProperties() as $property) {
            if ($this->reader->getPropertyAnnotation($property, Ignore::class)) {
                $ignoredColumns[] = $property->getName();
            }
        }

        return new AnnotationDto(
            $metadata->getName(),
            $auditableAnnotation->enabled,
            $ignoredColumns
        );
    }
}
