<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Contract;

use Drjele\DoctrineAudit\Dto\Revision\UserDto;

interface UserProviderInterface
{
    public function getUser(): UserDto;
}
