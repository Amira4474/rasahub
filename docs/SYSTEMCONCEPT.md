I want to create a new personal F&B POS and restaurant management system called **RasaHub**.

This is a brand-new project. Treat this as a production-quality application that I am building as a long-term personal project and portfolio project.

## Technology requirements

Use this technology stack:

* Laravel
* PHP version already installed in my WSL environment
* MySQL
* Filament for the administration/back-office UI
* Docker / Docker Desktop with WSL 2 integration
* Node.js/npm for frontend asset building
* Git/GitHub for source control

The development environment is:

* Windows
* WSL 2
* Ubuntu
* Homebrew inside WSL
* PHP 8.5.x
* Composer
* MySQL
* Node.js 24.x
* npm 11.x
* Git
* Docker Desktop for Windows with Ubuntu WSL integration
* Claude Code

Do not install a second Docker Engine inside WSL.

## IMPORTANT: Work in phases

Do NOT immediately build the POS modules.

First establish a clean and maintainable Laravel foundation.

Before executing major commands or installing packages, inspect the current environment and explain what you intend to do.

Avoid unnecessary packages and dependencies.

Do not introduce React, Vue, Inertia, or other frontend frameworks unless there is a clear architectural reason. Prefer Laravel, Blade, Filament, Livewire, Alpine.js and standard Laravel tooling where appropriate.

Keep the architecture simple, maintainable and understandable.

---

# Phase 1 — Project foundation

Create the Laravel application:

```
/home/amira/projects/rasahub
```

Use the latest stable Laravel version that is officially compatible with the installed PHP version.

Before creating the project, verify Laravel/PHP compatibility rather than assuming it.

Configure the project for MySQL.

Create a proper `.env` configuration for local development.

Do not commit `.env`.

Initialize Git.

Create a sensible `.gitignore`.

Create an initial commit once the foundation is working.

---

# Docker architecture

Docker Desktop for Windows is already installed and Ubuntu WSL integration is already enabled.

Do NOT install Docker Engine separately inside WSL.

## Docker files MUST be isolated

All Docker-related configuration for local development must live under:

```
/home/amira/projects/rasahub/docker-local
```

I want to be able to manage the local Docker environment from this directory.

The intended workflow should be:

```
cd ~/projects/rasahub/docker-local
```

Then:

```
docker compose up -d
docker compose down
docker compose ps
docker ps
```

Do not require me to `cd` to the Laravel project root just to run Docker Compose.

The Docker configuration should therefore be organized approximately like:

```
rasahub/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── docker-local/
│   ├── compose.yml
│   ├── .env
│   └── [other Docker-specific configuration]
├── artisan
├── composer.json
├── package.json
└── ...
```

The exact structure can differ if Laravel requires something else, but the principle is strict:

**All local Docker orchestration/configuration belongs under `docker-local/`.**

Do not scatter Docker Compose files, Docker environment files, Docker scripts or Docker-specific configuration around the Laravel root unless there is a technical requirement that cannot reasonably be avoided.

If a Dockerfile is required, place it under `docker-local/` or an appropriate subdirectory beneath `docker-local/`.

If Docker Compose needs to reference files outside `docker-local/`, use correct relative paths rather than duplicating configuration.

## Docker commands

The following should work from `docker-local`:

```
docker compose up -d
docker compose down
docker compose ps
```

And normal Docker commands such as:

```
docker ps
```

should work regardless of the current directory.

The Compose project should have a clear, predictable project/service naming convention using `rasahub`.

## Database

Evaluate whether MySQL should:

A. use the existing MySQL installation inside WSL, or
B. run as a Docker service.

I prefer a reproducible Docker-based development environment if it does not introduce unnecessary complexity.

Recommend one approach before implementing it.

If MySQL runs inside Docker, make sure persistent data survives:

```
docker compose down
```

but explain what happens with:

```
docker compose down -v
```

Do not accidentally design the setup so normal `docker compose down` destroys the database.

## Laravel Sail

Evaluate whether Laravel Sail is appropriate.

If Sail is used, its Docker configuration must still follow the `docker-local/` organization requirement as closely as technically possible.

If Sail's standard structure conflicts with this requirement, explain the conflict and recommend whether a custom Compose setup is cleaner.

Do not install Sail automatically without explaining the trade-offs.

## Services

Only include services that are genuinely required for the initial project.

Do not automatically add:

* Redis
* Elasticsearch
* Mailpit
* MinIO
* Meilisearch
* Selenium
* extra databases
* unnecessary queues

Additional services can be added later when there is a real requirement.

---

# Filament

Install the Filament version that is officially compatible with the selected Laravel version.

Verify Laravel, Livewire and Filament compatibility before installation.

Filament will primarily be used for:

* administration
* configuration
* master data
* reports
* staff management
* branch management
* products
* modifiers
* inventory
* customers
* system settings

The POS interface itself should NOT automatically be forced into a standard Filament CRUD interface.

We may later build a dedicated POS interface using Laravel/Livewire/Blade where that provides a better cashier experience.

---

# Initial architecture

Design RasaHub as a restaurant/F&B management platform rather than a simple CRUD POS.

The eventual system should support:

1. POS
2. Dine-in
3. Takeaway
4. Orders
5. Tables
6. Kitchen/KDS
7. Payments
8. Products
9. Categories
10. Modifiers
11. Modifier groups/templates
12. Branches
13. Terminals
14. Staff and roles
15. Customers
16. Inventory
17. Reports
18. QR ordering
19. Receipt printing
20. Offline operation
21. Synchronisation when connectivity returns

However, DO NOT build these modules yet.

First establish the underlying architecture.

---

# Multi-branch architecture

Design the database so the application can eventually support multiple restaurant branches.

A business may have:

* multiple branches
* multiple terminals per branch
* multiple users/staff
* branch-specific configuration
* branch-specific printers
* branch-specific products/settings where appropriate

Do not automatically introduce full Laravel tenancy/multi-database tenancy unless there is a strong reason.

Initially prefer a well-designed single application/database with explicit business/branch relationships.

Explain whether a tenancy package is actually necessary before installing one.

---

# Roles and permissions

Plan for roles such as:

* Super Admin
* Owner
* Manager
* Cashier
* Waiter
* Kitchen
* Inventory
* Supervisor

Use a proper permission system rather than hard-coding role checks throughout the application.

Do not implement all permissions yet.

Establish the architecture so permissions can later control access to:

* POS
* orders
* refunds
* discounts
* products
* inventory
* reports
* settings
* staff
* branches
* terminals

---

# POS architecture

The eventual POS must support restaurant workflows.

Think in terms of:

Branch
→ Terminal
→ Order
→ Order Items
→ Modifiers
→ Kitchen routing
→ Payment
→ Receipt

Orders should eventually support states such as:

* draft
* pending
* confirmed
* preparing
* ready
* completed
* cancelled

Payment state must be independent from order state.

Do not implement this yet; just design the foundation correctly.

---

# Offline capability

Offline operation is an important future requirement.

Do NOT implement offline mode in Phase 1.

However, the architecture must not make offline operation impossible later.

Consider future use of:

* browser IndexedDB
* local queue/outbox
* sync journal
* idempotency keys
* terminal identity
* conflict handling

Do not install an offline/PWA package yet.

Explain what architectural decisions now would make future offline synchronization easier.

---

# Printer architecture

Printing will eventually support:

* kitchen printers
* beverage/bar printers
* cashier/receipt printers

Do not implement printer integration yet.

However, avoid designing the application in a way that couples business logic directly to a particular printer or operating system.

Use a clear printing abstraction so a future print gateway can be added.

---

# KDS

A Kitchen Display System will eventually be required.

Do not build KDS yet.

The order/item model should eventually allow kitchen routing and item status tracking without tightly coupling it to the POS UI.

---

# QR ordering

QR ordering will eventually be supported.

Do not build it yet.

Design the product/order architecture so orders can eventually originate from:

* POS
* waiter
* QR ordering
* other supported channels

Avoid assuming every order comes from a cashier.

---

# Database design

Before creating many migrations, propose the core domain model.

At minimum consider:

* businesses
* branches
* terminals
* users
* roles
* permissions
* products
* categories
* modifiers
* modifier groups/templates
* tables
* orders
* order items
* payments
* customers
* printers
* settings

Do not blindly create every table above.

Review the relationships and identify which tables are genuinely required for Phase 1.

Use proper foreign keys, indexes, unique constraints and appropriate decimal types for monetary values.

Do not use floating-point numbers for money.

---

# Coding standards

Follow Laravel conventions.

Prefer:

* Form Requests
* Policies
* Actions/Services where business logic is substantial
* Eloquent relationships
* Events where appropriate
* Jobs for asynchronous work where appropriate
* database transactions for important financial operations
* enums/value objects where they genuinely improve clarity

Avoid:

* huge controllers
* huge Livewire components
* duplicated business logic
* unnecessary repository patterns
* unnecessary abstraction
* raw SQL when Eloquent/query builder is sufficient

Keep code readable.

---

# Security

Plan for:

* authorization
* validation
* CSRF protection
* mass-assignment protection
* secure authentication
* auditability of important financial actions
* protection against duplicate payments/orders

Do not weaken Laravel's security defaults.

---

# Testing

Use Laravel's supported testing tools.

At minimum, plan for:

* feature tests
* database tests
* authentication/authorization tests
* order calculations
* payment calculations
* discount calculations
* branch/terminal access

Do not attempt to create the entire test suite now.

Establish the testing structure from the beginning.

---

# UI direction

The application should eventually have:

* modern
* clean
* responsive
* mobile/tablet-friendly
* desktop-friendly

interfaces.

The POS screen should prioritize speed and usability over administration-style CRUD.

Filament should handle administration well, while the POS can have its own purpose-built interface.

Do not spend time on branding, animations or visual polish yet.

---

# Git

This is a personal GitHub project.

Use the Git identity already configured on this machine.

Do not change my Git identity.

Do not push anything to GitHub yet.

First create the local project and make sure the foundation works.

---

# VERY IMPORTANT working rules

1. Inspect before changing.
2. Explain major architectural decisions before implementing them.
3. Do not install unnecessary packages.
4. Do not create the entire POS in one step.
5. Do not modify unrelated files.
6. Preserve Laravel conventions.
7. Keep changes small and reviewable.
8. After important changes, run the appropriate tests/checks.
9. Tell me exactly what was changed.
10. If there are multiple reasonable architectural choices, stop and ask me before choosing.
11. Never expose secrets or credentials.
12. Do not modify my global machine configuration unless necessary.
13. Do not change my existing WSL, PHP, Node, MySQL, Git or Docker Desktop installation without asking.
14. Keep all RasaHub local Docker configuration under `docker-local/`.
15. Do not create Docker configuration in the user's home directory.
16. Do not install a second Docker Engine inside WSL.

---

# First task

For now, ONLY do the following:

1. Inspect the current environment.
2. Confirm the installed PHP, Composer, Node, npm, Git, MySQL and Docker versions/status.
3. Check Laravel installer availability.
4. Check Laravel's compatibility with the installed PHP version.
5. Propose the Laravel + Filament + Docker + MySQL architecture for RasaHub.
6. Recommend whether MySQL should run in Docker or use the existing WSL MySQL, with reasons.
7. Recommend whether Laravel Sail should be used.
8. Explain how the `docker-local/` structure will work.
9. Identify the exact packages you propose installing.
10. Do NOT create the project yet.
11. Do NOT modify the environment yet.
12. Do NOT install packages yet.

After presenting the plan, wait for my approval before proceeding.
