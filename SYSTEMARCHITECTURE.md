I want to clarify and change the Docker decision before you proceed.

I want RasaHub to use a **full Docker-based local development environment**, not just Docker for MySQL.

The intended workflow is:

```
cd ~/projects/rasahub/docker-local
docker compose up -d
```

Work on RasaHub while the Docker environment is running.

When finished:

```
docker compose down
```

The environment should be designed so that starting it again with:

```
docker compose up -d
```

restores the development environment and persistent database data.

### Docker architecture

Please containerise the RasaHub development environment from the beginning.

At minimum, evaluate these services:

* PHP/Laravel application container
* MySQL container
* Node/Vite development environment, if needed

Do NOT automatically add Redis, Mailpit, Elasticsearch, MinIO, Selenium, or other services. Only add a service if RasaHub genuinely needs it for Phase 1.

I want PHP/Laravel to run inside Docker rather than relying on the WSL host PHP runtime for the application.

The WSL host tools such as Git, Docker CLI, Composer/Node may still exist, but the RasaHub runtime should be reproducible through Docker.

### Docker file location — IMPORTANT

ALL Docker-related configuration must live under:

```
/home/amira/projects/rasahub/docker-local
```

I specifically want this workflow:

```
cd ~/projects/rasahub/docker-local
docker compose up -d
docker compose down
docker compose ps
```

Do not place compose.yml, Dockerfiles, Docker scripts, or other Docker configuration in the Laravel project root unless there is a genuine technical requirement.

If a Dockerfile or configuration needs to reference Laravel files outside docker-local/, use appropriate relative paths/build contexts. Do not duplicate Docker configuration.

### Persistence

MySQL data must use a named Docker volume.

Normal:

```
docker compose down
```

must NOT delete database data.

Only:

```
docker compose down -v
```

should remove the database volume and therefore destroy the local database data.

Document this clearly in docker-local/README.md.

### Ports

My existing WSL/Homebrew MySQL currently uses host port 3306.

Do not interfere with or modify that existing MySQL installation.

If the Docker MySQL container needs a host port for direct access, use 3307 unless there is a better reason to choose another port.

Within Docker, services should communicate using Docker Compose service names and container ports rather than unnecessarily routing through host ports.

### Laravel application

Use:

* Laravel 13.x
* PHP 8.5.x
* MySQL
* Filament 5.x compatible with Laravel 13
* the currently compatible Livewire version required by Filament 5
* spatie/laravel-permission 8.x

Before implementation, verify the exact compatible versions.

Do not use Laravel Sail if it conflicts with my requirement that all Docker configuration lives under docker-local/. A custom Docker Compose setup is preferred.

### Project structure

The intended structure should be approximately:

```
rasahub/
├── app / Laravel application files
├── docs/
│   └── SYSTEMCONCEPT.md
└── docker-local/
    ├── compose.yml
    ├── Dockerfile(s)
    ├── .env
    ├── .env.example
    ├── mysql/
    └── README.md
```

Do not create the final structure blindly; propose the exact structure first.

### SYSTEMCONCEPT.md

The existing SYSTEMCONCEPT.md is currently at the project root and docs/ is empty.

Move it to:

```
docs/SYSTEMCONCEPT.md
```

Preserve its contents exactly.

Do not rewrite, summarize, or alter the document.

Verify that the file contents are unchanged after moving it.

### Phase 1 scope

Do NOT build the actual POS yet.

Phase 1 should establish only the project foundation:

* Laravel application
* Docker development environment
* MySQL
* Filament
* authentication/foundation as appropriate
* roles and permissions foundation
* initial core database structure
* Git repository/configuration
* development documentation

Do not yet implement:

* POS sales
* products
* modifiers
* orders
* payments
* KDS
* printing
* QR ordering
* offline synchronization
* inventory
* reports

Those will be separate phases.

### Domain foundation

For the initial architecture, plan for:

* businesses
* branches
* terminals
* users
* branch/user relationships
* roles
* permissions

Keep these concepts explicit because RasaHub will eventually support multiple businesses/branches and physical POS terminals.

Do not introduce a full tenancy package at this stage unless you can demonstrate a concrete requirement for it.

### Identifiers and offline readiness

Do not automatically make ULIDs the primary key for every table.

Evaluate where public identifiers/ULIDs are useful versus where ordinary internal database primary keys are simpler.

The architecture should nevertheless be prepared for future:

* terminal-created records
* offline operation
* idempotent requests
* synchronization
* conflict handling

Orders and payments should eventually support client-generated idempotency keys.

Terminals should be first-class identities.

Order state and payment state must remain separate concepts.

Use DECIMAL for monetary values, never floating-point values for money.

### IMPORTANT: implementation process

Before creating or modifying anything, show me:

1. Exact Docker architecture.
2. Exact Docker services.
3. Exact Docker image/base-image choices.
4. Exact PHP version.
5. Exact MySQL version.
6. Exact Node version, if Node is containerized.
7. Exact Laravel version.
8. Exact Filament version.
9. Exact Livewire version.
10. Exact spatie/laravel-permission version.
11. Exact port mappings.
12. Docker volume strategy.
13. Docker network strategy.
14. How file mounting/live reload will work.
15. How Composer dependencies will be handled.
16. How Node/Vite will be handled.
17. Proposed project directory structure.
18. Proposed Phase 1 database schema.
19. Which files will be created, moved, or modified.
20. Exact commands I will use to start and stop the environment.

Pay particular attention to file permissions between Windows/WSL/Docker and avoid a setup that creates root-owned files in my WSL project.

### Do not implement yet

For now, this is still a design/approval step.

Do NOT:

* create the Laravel project
* install project dependencies
* create Docker files
* modify the database
* modify global configuration
* modify my existing Homebrew MySQL
* modify unrelated WSL/Docker configuration
* build application modules

First give me the revised architecture and implementation plan based on the full-Docker requirement above.

Then wait for my approval.
