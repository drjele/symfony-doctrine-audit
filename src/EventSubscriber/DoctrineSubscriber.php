<?php

declare(strict_types=1);

/*
 * Copyright (c) Adrian Jeledintan
 */

namespace Drjele\DoctrineAudit\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

class DoctrineSubscriber implements EventSubscriber
{
    public function getSubscribedEvents()
    {
        return [Events::postFlush];
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
    }
}
