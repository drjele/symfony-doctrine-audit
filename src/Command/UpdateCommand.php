<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Drjele\SymfonyCommand\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends AbstractCommand
{
    private EntityManagerInterface $sourceEntityManager;
    private EntityManagerInterface $destinationEntityManager;

    public function __construct(
        string $name,
        EntityManagerInterface $sourceEntityManager,
        EntityManagerInterface $destinationEntityManager
    ) {
        parent::__construct($name);

        $this->sourceEntityManager = $sourceEntityManager;
        $this->destinationEntityManager = $destinationEntityManager;
    }

    protected function configure()
    {
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    }
}
