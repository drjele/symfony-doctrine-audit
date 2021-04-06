<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Storage;

/** @todo add the option of extra data */
class TransactionDto
{
    private ?int $transactionId;

    public function __construct(?int $transactionId)
    {
        $this->transactionId = $transactionId;
    }

    public function getTransactionId(): ?int
    {
        return $this->transactionId;
    }
}
