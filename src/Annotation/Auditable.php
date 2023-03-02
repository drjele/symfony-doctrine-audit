<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 *
 * @Target("CLASS")
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Auditable extends Annotation
{
    public $value = true;
}
