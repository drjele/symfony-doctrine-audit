<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Storage;

use DateTime;
use Drjele\Doctrine\Audit\Contract\StorageInterface;
use Drjele\Doctrine\Audit\Dto\Storage\StorageDto;
use Drjele\Doctrine\Audit\Trait\ThrowTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Throwable;

final class FileStorage implements StorageInterface
{
    use ThrowTrait;

    public function __construct(
        private readonly string $file,
        private readonly ?LoggerInterface $logger,
    ) {}

    public function save(StorageDto $storageDto): void
    {
        try {
            $transaction = $this->buildTransaction($storageDto);

            $filesystem = new Filesystem();

            $filesystem->appendToFile($this->file, $transaction . \PHP_EOL);
        } catch (Throwable $t) {
            $this->throw($t);
        }
    }

    private function buildTransaction(StorageDto $storageDto): string
    {
        $entities = [];

        foreach ($storageDto->getEntities() as $entityDto) {
            $columns = [];

            foreach ($entityDto->getFields() as $columnDto) {
                $columns[$columnDto->getName()] = $columnDto->getValue();
            }

            $entities[] = [
                'class' => $entityDto->getClass(),
                'columns' => $columns,
            ];
        }

        $transaction = [
            'username' => $storageDto->getTransaction()->getUsername(),
            'date' => (new DateTime())->format('Y-m-d H:i:s'),
            'entities' => $entities,
        ];

        return (new JsonEncoder())->encode($transaction, JsonEncoder::FORMAT);
    }
}
