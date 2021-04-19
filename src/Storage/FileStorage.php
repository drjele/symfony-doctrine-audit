<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Storage;

use DateTime;
use Drjele\DoctrineAudit\Contract\StorageInterface;
use Drjele\DoctrineAudit\Dto\Storage\StorageDto;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class FileStorage implements StorageInterface
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function save(StorageDto $storageDto): void
    {
        $transaction = $this->buildTransaction($storageDto);

        $filesystem = new Filesystem();

        $filesystem->appendToFile($this->file, $transaction . \PHP_EOL);
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
