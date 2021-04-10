<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Command\DoctrineSchema;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class CreateCommand extends AbstractCommand
{
    private const DUMP_SQL = 'dump-sql';

    protected function configure()
    {
        parent::configure();

        $this->setDescription('create the database schema for the corresponding auditor')
            ->addOption(static::DUMP_SQL, null, InputOption::VALUE_NONE, 'output the sql');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $dumpSql = true === $input->getOption(static::DUMP_SQL);

            $sourceMetadatas = $this->sourceEntityManager->getMetadataFactory()->getAllMetadata();

            $schemaTool = $this->createSchemaTool();

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
