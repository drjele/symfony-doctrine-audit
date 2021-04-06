# Doctrine audit library

**You may fork and modify it as you wish**.

Any suggestions are welcomed.

**This is a work in progress**.

## Purpose

* have audit for specific entities.
* the audit database to have its own connection.
* use two step storage for failover and to not slow down the primary application thread.
* multiple storage types.

## Sample config

```yaml
drjele_doctrine_audit:
    storages:
        doctrine:
            type: doctrine
            entity_manager: default
        file:
            type: file
            file: '%kernel.project_dir%/var/audit.log'
        custom:
            type: custom
            service: Acme\Common\Service\AuditStorageService
    auditors:
        doctrine:
            entity_manager: default
            storage: doctrine
            user_provider: Acme\Common\Service\AuditUserProviderService
        file:
            entity_manager: default
            storage: file
            user_provider: Acme\Common\Service\AuditUserProviderService
        custom:
            entity_manager: default
            storage: custom
            user_provider: Acme\Common\Service\AuditUserProviderService
```

## Inspired by

* https://github.com/xiidea/EasyAuditBundle
* https://github.com/DamienHarper/auditor-bundle
* https://github.com/sonata-project/EntityAuditBundle

## Todo

* config.
* chain storages.
* logger.
* schema update.
