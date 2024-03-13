<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Drjele\Doctrine\Audit\Attribute\Auditable;
use Drjele\Doctrine\Audit\Attribute\Ignore;
use Drjele\Doctrine\Audit\Dto\Annotation\EntityDto;
use Drjele\Doctrine\Audit\Exception\Exception;
use ReflectionClass;
use ReflectionProperty;

class AnnotationReadService
{
    public static function getEntityClass(object $class): string
    {
        $class = \is_object($class) ? $class::class : $class;

        $proxyString = '\\__CG__\\';
        $proxyPosition = \mb_strrpos($class, $proxyString);
        if (false !== $proxyPosition) {
            return \mb_substr($class, $proxyPosition + \strlen($proxyString));
        }

        return $class;
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
                    \sprintf('duplicate annotation for entity class `%s`', $entityClass)
                );
            }

            $entities[$entityClass] = $entityDto;
        }

        return $entities;
    }

    public function buildEntityDto(ClassMetadata $classMetadata): ?EntityDto
    {
        $reflectionClass = $classMetadata->getReflectionClass() ?? new ReflectionClass($classMetadata->getName());

        if (false === $this->isEntity($reflectionClass)) {
            /* ignore non entity */
            return null;
        }

        if (false === $this->isAuditable($reflectionClass)) {
            /* ignore not auditable entity */
            return null;
        }

        $ignoredFields = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            /* @todo add global ignore columns config */

            if (false === $this->isIgnored($reflectionProperty)) {
                continue;
            }

            $field = $reflectionProperty->getName();

            if ($classMetadata->isIdentifier($field)) {
                /* identifiers are never ignored */
                continue;
            }

            $ignoredFields[] = $field;
        }

        return new EntityDto($classMetadata->getName(), $ignoredFields);
    }

    private function isAuditable(ReflectionClass $reflectionClass): bool
    {
        $attributes = $reflectionClass->getAttributes(Auditable::class);
        foreach ($attributes as $attribute) {
            /** @var Auditable $auditable */
            $auditable = $attribute->newInstance();

            return $auditable->enabled;
        }

        return false;
    }

    private function isEntity(ReflectionClass $reflectionClass): bool
    {
        $attributes = $reflectionClass->getAttributes(Entity::class);

        return empty($attributes) === false;
    }

    private function isIgnored(ReflectionProperty $reflectionProperty): bool
    {
        $attributes = $reflectionProperty->getAttributes(Ignore::class);
        foreach ($attributes as $attribute) {
            /** @var Ignore $ignore */
            $ignore = $attribute->newInstance();

            return $ignore->enabled;
        }

        return false;
    }
}
