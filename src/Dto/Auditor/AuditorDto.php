<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Dto\Auditor;

final class AuditorDto
{
    /** @var EntityDto[] */
    private array $auditEntities;

    public function __construct(
        private readonly array $entitiesToDelete,
        private readonly array $entitiesToInsert,
        private readonly array $entitiesToUpdate
    ) {
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
