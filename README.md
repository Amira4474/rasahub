# RasaHub

An F&B point-of-sale and restaurant management platform, built with Laravel,
Filament and MySQL.

This repository currently contains the **Phase 1 foundation** only: the
application skeleton, the Docker development environment, authentication, roles
and permissions, and the core business, branch, terminal and user structure. No
POS functionality exists yet.

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

The admin panel is at http://localhost:8080/admin.

## Domain foundation

- **Business**: the top-level owner of everything. Has many branches and users.
- **Branch**: a physical outlet of a business. Has many terminals.
- **Terminal**: a physical POS device in a branch, and a first-class identity.
- **User**: belongs to one business, or to none for a platform-level user.

Two separate things decide what a user can do:

- **Roles and permissions** say *what* a user may do. There is one global set of
  roles, defined in `app/Enums/Role.php`, with permissions in
  `app/Enums/Permission.php`. After changing either, run
  `php artisan db:seed` to sync the database.
- **Branch assignment** says *where* they may do it. It lives in the
  `branch_user` table. A user reaches only their assigned branches unless they
  hold the `branches.access-all` permission, which Owners do.

Super Admins pass every check through a single `Gate::before` rule in
`AppServiceProvider`.

## Conventions for later phases

- Integer primary and foreign keys everywhere. Businesses, branches and
  terminals also carry a `ulid` column as a public identifier.
- Records a terminal can create (orders, payments) will carry a
  client-generated ULID and an idempotency key.
- Order state and payment state are separate concepts.
- Money is stored as `DECIMAL`, never as a float.

## Documents

- [docs/SYSTEMCONCEPT.md](docs/SYSTEMCONCEPT.md): the original system concept.
- [SYSTEMARCHITECTURE.md](SYSTEMARCHITECTURE.md): the full-Docker architecture brief.
