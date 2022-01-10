<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Dto\Storage;

/** @todo add the option of extra data */
final class TransactionDto
{
    private string $username;

    public function __construct(
        string $username
    ) {
        $this->username = $username;
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
