# Doctrine audit library

**You may fork and modify it as you wish**.

Any suggestions are welcomed.

**This is work in progress**.

## Purpose

* have audit for specific entities.
* the audit database to have its own connection.
* use two step storage for failover and to not slow down the primary application thread.
* multiple storage types.

## Inspired by

* https://github.com/xiidea/EasyAuditBundle
* https://github.com/DamienHarper/auditor-bundle
* https://github.com/sonata-project/EntityAuditBundle

## Todo

* config
* subscriber
* different storages
* chained storages
* entity difference dto
* logger
