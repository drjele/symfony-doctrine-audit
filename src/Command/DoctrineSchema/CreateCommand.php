<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Command\DoctrineSchema;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class CreateCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('create the database schema for the corresponding auditor');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->warning('careful when running this in a production environment');

            $sourceMetadatas = $this->sourceEntityManager->getMetadataFactory()->getAllMetadata();

            $schemaTool = $this->createSchemaTool();

            $this->writeln('the following sql statements will be executed');

            $sqls = $schemaTool->getCreateSchemaSql($sourceMetadatas);

            foreach ($sqls as $sql) {
                $this->style->writeln(\sprintf('    %s;', $sql));
            }

            $this->writeln('----------------------------------------------------------------------');

            $force = true === $input->getOption(static::FORCE);
            if ($force) {
                $this->writeln('creating database schema');

                $schemaTool->createSchema($sourceMetadatas);

                $this->success('database schema created successfully');
            }
        } catch (Throwable $t) {
            $this->error($t->getMessage(), $t, true);

            return static::FAILURE;
        }

        return static::SUCCESS;
    }
}
