<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Dto\Storage;

/** @info this and all of its children should be read only */
final class StorageDto
{
    private TransactionDto $transaction;
    private array $entities;

    public function __construct(TransactionDto $transaction, array $entities)
    {
        $this->transaction = $transaction;
        $this->entities = $entities;
    }

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
