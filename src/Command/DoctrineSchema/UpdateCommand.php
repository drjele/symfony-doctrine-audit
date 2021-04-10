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
            $force = true === $input->getOption(static::FORCE);

            $sourceMetadatas = $this->sourceEntityManager->getMetadataFactory()->getAllMetadata();

            $schemaTool = $this->createSchemaTool();

            $sqls = $schemaTool->getUpdateSchemaSql($sourceMetadatas, true);

            if (empty($sqls)) {
                $this->success('nothing to update');

                return static::SUCCESS;
            }

            $this->writeln('the following sql statements will be executed');

            foreach ($sqls as $sql) {
                $this->io->writeln(\sprintf('    %s;', $sql));
            }

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
