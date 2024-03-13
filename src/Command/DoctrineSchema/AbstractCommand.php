<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Command\DoctrineSchema;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractCommand extends \Drjele\Symfony\Console\Command\AbstractCommand
{
    protected const FORCE = 'force';

    public function __construct(
        string $name,
        protected readonly EntityManagerInterface $sourceEntityManager,
        protected readonly EntityManagerInterface $destinationEntityManager
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->addOption(static::FORCE, null, InputOption::VALUE_NONE, 'run the sql');
    }

    protected function createSchemaTool(): SchemaTool
    {
        /** @todo only consider audited entities */
        $sourceMetadatas = $this->sourceEntityManager->getMetadataFactory()->getAllMetadata();

        foreach ($sourceMetadatas as $classMetadata) {
            $this->destinationEntityManager->getMetadataFactory()
                ->setMetadataFor($classMetadata->getName(), $classMetadata);
        }

        return new SchemaTool($this->destinationEntityManager);
    }
}
