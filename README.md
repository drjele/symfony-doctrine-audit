# Doctrine audit library

**You may fork and modify it as you wish**.

Any suggestions are welcomed.

**This is a work in progress**. **ONLY WORKS AFTER TAG 2.1**.

## Todo

* Unit tests.
* Retain old values in case the values are changed directly from database.

## Usage

### Sample config and storage

```yaml
drjele_doctrine_audit:
    storages:
        doctrine_one:
            type: doctrine
            entity_manager: audit_em_one
            config: # \Drjele\Doctrine\Audit\Storage\Doctrine\Configuration
                transaction_table_name: 'audit_transaction'
        file:
            type: file
            file: '%kernel.project_dir%/var/audit.log'
        doctrine_two:
            type: doctrine
            entity_manager: audit_em_two
            config: # \Drjele\Doctrine\Audit\Storage\Doctrine\Configuration
                transaction_table_name: 'audit_transaction'
        rabbit:
            type: custom
            service: Acme\Shared\Service\AuditStorageService
    auditors:
        doctrine:
            entity_manager: source_em_one
            storages:
                - doctrine
            transaction_provider: Acme\Shared\Service\AuditTransactionProviderService
            logger: monolog.logger
            ignored_fields:
                - created
                - modified
        file:
            entity_manager: source_em_two
            storages:
                - file
            transaction_provider: Acme\Shared\Service\AuditTransactionProviderService
        async:
            entity_manager: source_em_three
            storages:
                - doctrine_two
                - rabbit
            synchronous_storages:
                - rabbit # the rabbit storage will publish the storage dto and a consumer will be required to save to the doctrine storage
            transaction_provider: Acme\Shared\Service\AuditTransactionProviderService
```

```yaml
# services.yaml
services:
    Acme\Shared\Service\AuditStorageService:
        arguments:
            $storage: '@drjele_doctrine_audit.storage.doctrine_two'
```

```php
<?php

declare(strict_types=1);

namespace Acme\Shared\Service;

use Drjele\Doctrine\Audit\Contract\TransactionProviderInterface;
use Drjele\Doctrine\Audit\Dto\Storage\TransactionDto;

final class AuditTransactionProviderService implements TransactionProviderInterface
{
    public function getTransaction(): TransactionDto
    {
        $username = '~';

        /* @todo implement application specific logic */

        return new TransactionDto($username);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Acme\Shared\Service;

use Drjele\Doctrine\Audit\Contract\StorageInterface;
use Drjele\Doctrine\Audit\Dto\Storage\StorageDto;
use Drjele\Doctrine\Audit\Storage\Doctrine\Storage;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

class AuditStorageService implements StorageInterface
{
    private SerializerInterface $serializer;
    private Storage $storage;
    private ProducerInterface $producer;
    private LoggerInterface $logger;
    private ThrowableHandlerService $throwableHandlerService;

    public function __construct(
        SerializerInterface $serializer,
        Storage $storage,
        ProducerInterface $auditProducer,
        LoggerInterface $logger,
        ThrowableHandlerService $throwableHandlerService
    ) {
        $this->serializer = $serializer;
        $this->storage = $storage;
        $this->producer = $auditProducer;
        $this->logger = $logger;
        $this->throwableHandlerService = $throwableHandlerService;
    }

    public function save(StorageDto $storageDto): void
    {
        try {
            $message = $this->serializer->serialize($storageDto, JsonEncoder::FORMAT);

            $this->producer->publish($message);
        } catch (Throwable $t) {
            $context = $this->throwableHandlerService->getContext($t);
            /* @todo serialize by hand */
            $context['dto'] = $message ?? 'could not serialize';

            $this->logger->error($t->getMessage(), $context);
        }
    }

    public function consume(AMQPMessage $message): void
    {
        /** @var StorageDto $storageDto */
        $storageDto = $this->serializer->deserialize($message->getBody(), StorageDto::class, JsonEncoder::FORMAT);

        $this->storage->save($storageDto);
    }
}
```

### Doctrine storage

This library will register two commands for each auditor with a **doctrine type storage**:

* ``drjele:doctrine:audit:schema:create:<em-name>`` - will create the audit database schema for auditor **default**.
* ``drjele:doctrine:audit:schema:update:<em-name>`` - will update the audit database schema for auditor **default**.

## Dev

* `git clone git@gitlab.com:drjele-symfony/doctrine-audit.git`
* `cd command/scripts/docker/`
* `cp ~/.ssh/id_* ./`
* `create .profile_personal with:`

```
echo 'git config --global user.name "<your name>"' >> ./.profile_personal && \
echo 'git config --global user.email "<your email>"' >> ./.profile_personal
```

* `docker-compose build && docker-compose up -d`
* `docker-compose exec php sh`
* `composer install`

## Inspired by

* https://github.com/xiidea/EasyAuditBundle
* https://github.com/DamienHarper/auditor-bundle
* https://github.com/sonata-project/EntityAuditBundle
