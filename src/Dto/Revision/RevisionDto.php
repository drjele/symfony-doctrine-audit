<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Revision;

class RevisionDto
{
    private UserDto $user;
    private array $deletions;
    private array $insertions;
    private array $updates;

    public function __construct(UserDto $user, array $deletions, array $insertions, array $updates)
    {
        $this->user = $user;
        $this->deletions = $deletions;
        $this->insertions = $insertions;
        $this->updates = $updates;
    }

    public function getUser(): ?UserDto
    {
        return $this->user;
    }

    /** @return EntityDto[] */
    public function getDeletions(): ?array
    {
        return $this->deletions;
    }

    /** @return EntityDto[] */
    public function getInsertions(): ?array
    {
        return $this->insertions;
    }

    /** @return EntityDto[] */
    public function getUpdates(): ?array
    {
        return $this->updates;
    }
}
