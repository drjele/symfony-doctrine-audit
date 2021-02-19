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

namespace Drjele\DoctrineAudit\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target(Target::TARGET_PROPERTY)
 */
final class Ignore extends Annotation
{
}
