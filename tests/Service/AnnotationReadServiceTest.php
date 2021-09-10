<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Test\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Drjele\Doctrine\Audit\Dto\Annotation\EntityDto;
use Drjele\Doctrine\Audit\Service\AnnotationReadService;
use Drjele\Doctrine\Audit\Test\Utility\OneEntity;
use Drjele\Doctrine\Audit\Test\Utility\TwoEntity;
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
            ['fakeParamToCallContructor'],
            true
        );
    }

    public function test(): void
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

        $entityManagerInterfaceMock = Mockery::mock(EntityManagerInterface::class);
        $entityManagerInterfaceMock->shouldReceive('getMetadataFactory')
            ->once()
            ->andReturnSelf();
        $entityManagerInterfaceMock->shouldReceive('getAllMetadata')
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
