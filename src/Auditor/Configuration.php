<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Auditor;

final class Configuration
{
    public function __construct(
        private readonly array $ignoredFields
    ) {}

    public function getIgnoredFields(): ?array
    {
        return $this->ignoredFields;
    }
}
