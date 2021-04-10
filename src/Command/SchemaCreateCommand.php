<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Drjele\SymfonyCommand\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class SchemaCreateCommand extends AbstractCommand
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

        $this->setDescription('create the database schema for the corresponding auditor')
            ->addOption('dump-sql', null, InputOption::VALUE_NONE, 'output the sql');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $dumpSql = true === $input->getOption('dump-sql');

            $sourceMetadatas = $this->sourceEntityManager->getMetadataFactory()->getAllMetadata();

            $schemaTool = new SchemaTool($this->destinationEntityManager);

            if ($dumpSql) {
                $sqls = $schemaTool->getCreateSchemaSql($sourceMetadatas);

                foreach ($sqls as $sql) {
                    $this->io->writeln(\sprintf('    %s;', $sql));
                }

                return static::SUCCESS;
            }

            $this->warning('this operation should not be executed in a production environment');

            $this->writeln('creating database schema');

            $schemaTool->createSchema($sourceMetadatas);

            $this->success('database schema created successfully');
        } catch (Throwable $t) {
            $this->error($t->getMessage());

            return static::FAILURE;
        }

        return static::SUCCESS;
    }
}
