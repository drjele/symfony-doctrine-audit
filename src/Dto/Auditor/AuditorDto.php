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
    private array $auditEntities;

    public function __construct(
        array $entitiesToDelete,
        array $entitiesToInsert,
        array $entitiesToUpdate
    ) {
        $this->entitiesToDelete = $entitiesToDelete;
        $this->entitiesToInsert = $entitiesToInsert;
        $this->entitiesToUpdate = $entitiesToUpdate;
        $this->auditEntities = [];
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
    public function getAuditEntities(): ?array
    {
        return $this->auditEntities;
    }

    public function addAuditEntity(EntityDto $entityDto): self
    {
        $this->auditEntities[] = $entityDto;

        return $this;
    }
}
