<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\Dto\Revision;

class EntityDto
{
    private $entity;

    public function __construct($entity)
    {
        $this->entity = $entity;
    }

    public function getEntity()
    {
        return $this->entity;
    }
}
