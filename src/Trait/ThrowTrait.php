<?php

declare(strict_types=1);

namespace Drjele\Doctrine\Audit\Trait;

use Drjele\Doctrine\Audit\Exception\Exception;
use Throwable;

trait ThrowTrait
{
    private function throw(Throwable $t, array $logContext = []): void
    {
        if (null !== $this->logger) {
            $this->logger->error(
                __CLASS__ . ': ' . $t->getMessage(),
                $logContext + [
                    'code' => $t->getCode(),
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                    'trace' => $t->getTrace(),
                ]
            );
        }

        throw new Exception($t->getMessage(), $t->getCode(), $t);
    }
}
