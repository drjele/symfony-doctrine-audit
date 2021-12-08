# Doctrine audit library

**You may fork and modify it as you wish**.

Any suggestions are welcomed.

**This is a work in progress**. **ONLY WORKS AFTER TAG 2.1**.

## Sample config

```yaml
drjele_doctrine_audit:
    storages:
        doctrine:
            type: doctrine
            entity_manager: audit_em
            config: # \Drjele\Doctrine\Audit\Storage\Doctrine\Configuration
                transaction_table_name: 'audit_transaction'
        file:
            type: file
            file: '%kernel.project_dir%/var/audit.log'
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
                - doctrine
                - rabbit
            synchronous_storages:
                - rabbit # the rabbit storage will publish the StorageDto and a consumer will be required to save to the doctrine storage
            transaction_provider: Acme\Shared\Service\AuditTransactionProviderService
```

## Doctrine storage

This library will register two commands for each auditor with a **doctrine type storage**:

* ``drjele:doctrine:audit:schema:create:<em-name>`` - will create the audit database schema for auditor **default**.
* ``drjele:doctrine:audit:schema:update:<em-name>`` - will update the audit database schema for auditor **default**.

## Todo

* Unit tests.
* catch updates done with \Doctrine\ORM\EntityManagerInterface::getReference.

## Purpose

* Use two step storage for failover and to not slow down the primary application thread.

## Inspired by

* https://github.com/xiidea/EasyAuditBundle
* https://github.com/DamienHarper/auditor-bundle
* https://github.com/sonata-project/EntityAuditBundle

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
