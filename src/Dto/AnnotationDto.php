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

namespace Drjele\DoctrineAudit\Dto;

class AnnotationDto
{
    private string $entity;
    private bool $enabled;
    private array $ignoredColumns;

    public function __construct(string $entity, bool $enabled, array $ignoredColumns)
    {
        $this->entity = $entity;
        $this->enabled = $enabled;
        $this->ignoredColumns = $ignoredColumns;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function getIgnoredColumns(): ?array
    {
        return $this->ignoredColumns;
    }
}
