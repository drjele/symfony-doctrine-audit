<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Auditable
{
    public function __construct(
        public readonly bool $enabled = true
    ) {}
}
