<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Dto\Storage;

/** this and all of its children should be read only */
final class StorageDto
{
    public function __construct(
        private readonly TransactionDto $transaction,
        private readonly array $entities
    ) {}

    public function getTransaction(): ?TransactionDto
    {
        return $this->transaction;
    }

    /** @return EntityDto[] */
    public function getEntities(): ?array
    {
        return $this->entities;
    }
}
