<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Revision;

class RevisionDto
{
    private UserDto $user;
    /** @var EntityDto[] */
    private array $entities;

    public function __construct(UserDto $user, array $entities)
    {
        $this->user = $user;
        $this->entities = $entities;
    }

    public function getUser(): ?UserDto
    {
        return $this->user;
    }

    /** @return EntityDto[] */
    public function getEntities(): ?array
    {
        return $this->entities;
    }
}
