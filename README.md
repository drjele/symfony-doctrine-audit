# Doctrine audit library

**You may fork and modify it as you wish**.

Any suggestions are welcomed.

**This is a work in progress**.

## Sample config

```yaml
drjele_doctrine_audit:
    storages:
        doctrine:
            type: doctrine
            entity_manager: audit_em
            config: # \Drjele\DoctrineAudit\Storage\Doctrine\Config
                transaction_table_name: 'audit_transaction'
        file:
            type: file
            file: '%kernel.project_dir%/var/audit.log'
        custom:
            type: custom
            service: Acme\Common\Service\AuditStorageService
    auditors:
        doctrine:
            entity_manager: source_em_one
            storage: doctrine
            transaction_provider: Acme\Common\Service\AuditTransactionProviderService
            logger: monolog.logger
            ignored_fields:
                - created
                - modified
        file:
            entity_manager: source_em_two
            storage: file
            transaction_provider: Acme\Common\Service\AuditTransactionProviderService
        custom:
            entity_manager: source_em_three
            storage: custom
            transaction_provider: Acme\Common\Service\AuditTransactionProviderService
```

## Doctrine storage

This library will register two commands for each auditor with a **doctrine type storage**.
* ``drjele:doctrine:audit:schema:create:default`` - will create the audit database schema for auditor **default**.
* ``drjele:doctrine:audit:schema:update:default`` - will update the audit database schema for auditor **default**.

## Todo

* chain storages.

## Purpose

* use two step storage for failover and to not slow down the primary application thread.

## Inspired by

* https://github.com/xiidea/EasyAuditBundle
* https://github.com/DamienHarper/auditor-bundle
* https://github.com/sonata-project/EntityAuditBundle
