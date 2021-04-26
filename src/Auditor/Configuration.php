<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Auditor;

final class Configuration
{
    private array $ignoredFields;

    public function __construct(array $ignoredFields)
    {
        $this->ignoredFields = $ignoredFields;
    }

    public function getIgnoredFields(): ?array
    {
        return $this->ignoredFields;
    }
}
