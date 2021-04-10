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

class UpdateCommand extends AbstractCommand
{
    private const FORCE = 'force';

    protected function configure()
    {
        parent::configure();

        $this->setDescription('update the database schema for the corresponding auditor')
            ->addOption(static::FORCE, null, InputOption::VALUE_NONE, 'run the sql');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->warning('careful when running this in a production environment');

            $sourceMetadatas = $this->sourceEntityManager->getMetadataFactory()->getAllMetadata();

            $schemaTool = $this->createSchemaTool();

            $this->writeln('the following sql statements will be executed');

            $sqls = $schemaTool->getUpdateSchemaSql($sourceMetadatas, true);

            foreach ($sqls as $sql) {
                $this->io->writeln(\sprintf('    %s;', $sql));
            }

            $this->writeln('---------------------------------------------------------');

            $force = true === $input->getOption(static::FORCE);
            if ($force) {
                $this->writeln('updating database schema');

                $schemaTool->updateSchema($sourceMetadatas, true);

                $this->success('database schema updated successfully');
            }
        } catch (Throwable $t) {
            $this->error($t->getMessage());

            return static::FAILURE;
        }

        return static::SUCCESS;
    }
}
