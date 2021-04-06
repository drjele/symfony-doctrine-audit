<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Revision;

/** @todo add the option of extra data */
class UserDto
{
    private ?int $userId;

    public function __construct(?int $userId)
    {
        $this->userId = $userId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }
}
