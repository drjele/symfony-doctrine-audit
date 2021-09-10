<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\Doctrine\Audit\Test\Utility;

use Doctrine\ORM\Mapping as ORM;
use Drjele\Doctrine\Audit\Annotation\Auditable;

/**
 * @Auditable()
 * @ORM\Entity()
 */
class OneEntity
{
    private int $id;

    private string $name;

    private string $description;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
