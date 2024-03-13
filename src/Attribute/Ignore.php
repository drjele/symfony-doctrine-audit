<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Ignore
{
    public function __construct(
        public readonly bool $enabled = true
    ) {}
}
