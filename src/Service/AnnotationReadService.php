<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Service;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Drjele\Doctrine\Audit\Annotation\Auditable;
use Drjele\Doctrine\Audit\Annotation\Ignore;
use Drjele\Doctrine\Audit\Dto\Annotation\EntityDto;
use Drjele\Doctrine\Audit\Exception\Exception;

class AnnotationReadService
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

        if (false === $auditableAnnotation->value) {
            return null;
        }

        $ignoredFields = [];

        foreach ($reflection->getProperties() as $property) {
            /* @todo add global ignore columns config */

            if (true !== $this->shouldIgnore($property)) {
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

    private function shouldIgnore(\ReflectionProperty $field): bool
    {
        /** @var Annotation $ignore */
        $ignore = $this->reader->getPropertyAnnotation($field, Ignore::class);

        if (null === $ignore) {
            return false;
        }

        return true === $ignore->value;
    }
}
