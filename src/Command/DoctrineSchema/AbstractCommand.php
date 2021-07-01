<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Command\DoctrineSchema;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractCommand extends \Drjele\SymfonyCommand\Command\AbstractCommand
{
    protected const FORCE = 'force';

    protected EntityManagerInterface $sourceEntityManager;
    protected EntityManagerInterface $destinationEntityManager;

    public function __construct(
        string $name,
        EntityManagerInterface $sourceEntityManager,
        EntityManagerInterface $destinationEntityManager
    ) {
        parent::__construct($name);

        $this->sourceEntityManager = $sourceEntityManager;
        $this->destinationEntityManager = $destinationEntityManager;
    }

    protected function configure(): void
    {
        parent::configure();

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
