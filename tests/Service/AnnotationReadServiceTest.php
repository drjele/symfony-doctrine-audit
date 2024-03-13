<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Test\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata as MappingClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Drjele\Doctrine\Audit\Dto\Annotation\EntityDto;
use Drjele\Doctrine\Audit\Service\AnnotationReadService;
use Drjele\Doctrine\Audit\Test\Entity\OneEntity;
use Drjele\Doctrine\Audit\Test\Entity\TwoEntity;
use Drjele\Symfony\Phpunit\MockDto;
use Drjele\Symfony\Phpunit\TestCase\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionClass;

/**
 * @internal
 */
final class AnnotationReadServiceTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(
            AnnotationReadService::class,
            [],
            true
        );
    }

    public function testBuildEntityDto()
    {
        $entities = [
            OneEntity::class => [],
            TwoEntity::class => ['id', 'description'],
        ];

        /** @var AnnotationReadService|MockInterface $mock */
        $mock = $this->get(AnnotationReadService::class);

        foreach ($entities as $entity => $ignoredFields) {
            $classMetadata = new MappingClassMetadata($entity);

            $entityDto = $mock->buildEntityDto($classMetadata);

            static::assertSame($entity, $entityDto->getClass());
            static::assertSame($ignoredFields, $entityDto->getIgnoredFields());
        }
    }

    public function testRead(): void
    {
        $metadataOne = Mockery::mock(ClassMetadata::class);
        $metadataOne->shouldReceive('getReflectionClass')
            ->once()
            ->andReturn(new ReflectionClass(new OneEntity()));
        $metadataOne->shouldReceive('getName')
            ->once()
            ->andReturn(OneEntity::class);

        $metadataTwo = Mockery::mock(ClassMetadata::class);
        $metadataTwo->shouldReceive('getReflectionClass')
            ->once()
            ->andReturn(new ReflectionClass(new TwoEntity()));
        $metadataTwo->shouldReceive('getName')
            ->once()
            ->andReturn(TwoEntity::class);
        $metadataTwo->shouldReceive('isIdentifier')
            ->once()
            ->with('id')
            ->andReturn(true);
        $metadataTwo->shouldReceive('isIdentifier')
            ->once()
            ->with('description')
            ->andReturn(false);

        /** @var AnnotationReadService|MockInterface $mock */
        $mock = $this->get(AnnotationReadService::class);

        $classMetadataFactoryMock = Mockery::mock(ClassMetadataFactory::class);

        $entityManagerInterfaceMock = Mockery::mock(EntityManagerInterface::class);
        $entityManagerInterfaceMock->shouldReceive('getMetadataFactory')
            ->once()
            ->andReturn($classMetadataFactoryMock);
        $classMetadataFactoryMock->shouldReceive('getAllMetadata')
            ->once()
            ->andReturn([$metadataOne, $metadataTwo]);

        $entityDtos = $mock->read($entityManagerInterfaceMock);

        static::assertIsArray($entityDtos);

        foreach ($entityDtos as $entityDto) {
            $expected = TwoEntity::class === $entityDto->getClass() ? 1 : 0;

            static::assertInstanceOf(EntityDto::class, $entityDto);
            static::assertSame($expected, \count($entityDto->getIgnoredFields()));
        }
    }
}
