# RasaHub

An F&B point-of-sale and restaurant management platform, built with Laravel,
Filament and MySQL.

This repository currently contains the **Phase 1 foundation** only: the
application skeleton, the Docker development environment, the public website,
the three sign-in doors (platform, tenant admin, staff POS), roles and
permissions, tenant isolation, Super Impose and the core business, branch,
terminal and user structure. No selling functionality exists yet.

## Stack

| | Version |
|---|---|
| PHP | 8.5 |
| Laravel | 13 |
| Filament | 5 |
| Livewire | 4 |
| spatie/laravel-permission | 8 |
| MySQL | 9.7 (LTS) |
| Node | 24 |

## Running it

The whole runtime is in Docker. See [docker-local/README.md](docker-local/README.md)
for setup, daily commands and what each command does to your data.

```
cd docker-local
docker compose up -d
```

| | URL |
|---|---|
| Public website | http://localhost |
| Platform panel | http://localhost/platform |
| Tenant admin panel | http://localhost/admin |
| Staff POS | http://localhost/pos |

## Domain foundation

- **Platform**: RasaHub itself, above every business. Platform users belong
  to no business.
- **Business**: a tenant. Has many branches and users, a subscription, and
  a database of its own for its branches, terminals and later its menus and
  orders. Users, subscriptions and the businesses themselves are in the
  central database.
- **Branch**: a physical outlet of a business. Has many terminals.
- **Terminal**: a physical POS device in a branch, and a first-class identity.
- **User**: belongs to exactly one business, or to none for a platform user.

Who can reach what, the databases, tenant isolation, Super Impose,
subscriptions, invitations and migrations are described in
[docs/ACCESS.md](docs/ACCESS.md). Read it before adding a model or a screen.

## Conventions for later phases

- Integer primary and foreign keys everywhere. Businesses, branches and
  terminals also carry a `ulid` column as a public identifier.
- Records a terminal can create (orders, payments) will carry a
  client-generated ULID and an idempotency key.
- Order state and payment state are separate concepts.
- Money is stored as `DECIMAL`, never as a float.

## Documents

- [docs/ACCESS.md](docs/ACCESS.md): how access, tenancy, Super Impose, subscriptions, invitations and migrations work.
- [docs/CHANGELOG.md](docs/CHANGELOG.md): what changed in each version. The current version is in [VERSION.md](VERSION.md).
- [docs/SYSTEMCONCEPT.md](docs/SYSTEMCONCEPT.md): the original system concept.
- [docs/DifferentAccess.md](docs/DifferentAccess.md): the access requirements.
- [SYSTEMARCHITECTURE.md](SYSTEMARCHITECTURE.md): the full-Docker architecture brief.
