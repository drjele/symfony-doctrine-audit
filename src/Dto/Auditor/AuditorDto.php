<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Auditor;

class AuditorDto
{
    private array $entitiesToDelete;
    private array $entitiesToInsert;
    private array $entitiesToUpdate;
    /** @var EntityDto[] */
    private array $entities;

    public function __construct(
        array $entitiesToDelete,
        array $entitiesToInsert,
        array $entitiesToUpdate
    ) {
        $this->entitiesToDelete = $entitiesToDelete;
        $this->entitiesToInsert = $entitiesToInsert;
        $this->entitiesToUpdate = $entitiesToUpdate;
        $this->entities = [];
    }

    public function getEntitiesToDelete(): ?array
    {
        return $this->entitiesToDelete;
    }

    public function getEntitiesToInsert(): ?array
    {
        return $this->entitiesToInsert;
    }

    public function getEntitiesToUpdate(): ?array
    {
        return $this->entitiesToUpdate;
    }

    /** @return EntityDto[] */
    public function getEntities(): ?array
    {
        return $this->entities;
    }

    public function addEntity(EntityDto $entityDto): self
    {
        $this->entities[] = $entityDto;

        return $this;
    }
}
