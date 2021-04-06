<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Revision;

/** @info this and all of its children should be read only */
class RevisionDto
{
    private UserDto $userDto;
    private array $entities;

    public function __construct(UserDto $userDto, array $entities)
    {
        $this->userDto = $userDto;
        $this->entities = $entities;
    }

    public function getUserDto(): ?UserDto
    {
        return $this->userDto;
    }

    /** @return EntityDto[] */
    public function getEntities(): ?array
    {
        return $this->entities;
    }
}
