RasaHub is a completely new project.

Important context:

* There is NO VectorDine source code, script, database, migration, model, component, or implementation stored on this laptop.
* Do NOT search for or assume that VectorDine files exist locally.
* Do NOT copy, import, reuse, or reference VectorDine code.
* VectorDine is being used ONLY as a product/architecture reference for concepts and workflows that I want RasaHub to eventually support.
* RasaHub must be designed and implemented cleanly from scratch using Laravel/Filament and the architecture we have already approved.
* Do not try to recreate VectorDine's existing code structure. Recreate the intended concepts in a cleaner, new architecture suitable for RasaHub.

## 1. Overall RasaHub hierarchy

Design RasaHub around this hierarchy:

Platform
→ Businesses / Tenants
→ Branches / Outlets
→ POS Terminals / KDS / other operational devices
→ Users / Staff

There must be a clear distinction between:

1. Platform-level administration
2. Business/tenant administration
3. Branch-level access
4. POS/operational access

A platform administrator is NOT simply a normal tenant user with access to every business.

---

## 2. Platform layer

RasaHub should have a platform layer above the businesses/tenants.

Platform Super Admin should eventually be able to:

* see all businesses/tenants
* view tenant status
* view subscription/status information
* monitor tenant health
* identify potentially at-risk tenants
* test tenant connectivity
* access diagnostics
* access setup/runbook tools
* eventually monitor offline hubs/fleet health
* eventually manage signed authentication, synchronization, journals/recovery and backups
* enter a tenant through the Super Impose feature described below

Do NOT implement all of these future operational features now.

For Phase 1, establish the architectural foundation so these capabilities can be added later without redesigning the tenancy/access model.

---

## 3. Platform Super Admin vs tenant users

Use the following principle:

### Platform users

Platform administrators belong to the RasaHub platform itself.

They are NOT required to belong to a business.

Therefore:

* `users.business_id` may be NULL for platform administrators.
* Do NOT create a fake "Platform" business just to contain platform administrators.
* Platform-level permissions determine what they can do at platform level.

### Normal tenant users

A normal tenant/business user belongs to exactly ONE business.

Use:

`users.business_id`

for the user's business ownership/scope.

Do NOT introduce a `business_user` pivot at this stage.

A normal tenant user may have access to one or more branches through:

`branch_user`

Therefore:

* Business determines WHICH business the user belongs to.
* Branch assignment determines WHICH branches they can access.
* Role/permission determines WHAT they can do.

Keep these concepts separate.

---

# 4. Platform Super Impose

One important VectorDine concept that RasaHub should support is **Platform Super Impose**.

This must be designed as a first-class architectural concept, but implemented cleanly from scratch.

The intended workflow is:

Platform Super Admin
→ selects a business/tenant
→ chooses "Super Impose"
→ enters that tenant's administrative context
→ can operate within that tenant as an elevated platform administrator
→ exits Super Impose
→ returns to the platform context

### Very important:

Super Impose MUST NOT modify:

`users.business_id`

Do not permanently attach the platform administrator to the tenant.

Do not log the platform administrator out and pretend they are a tenant user.

Do not create a fake tenant-user relationship.

Instead, create a temporary authenticated tenant context/session.

The system should retain the actual platform identity while also recording the currently selected tenant context.

Conceptually:

```text
Authenticated identity:
    Platform Super Admin

Current context:
    Platform

Super Impose:
    Active

Selected business:
    Business A
```

When Super Impose is exited:

```text
Authenticated identity:
    Platform Super Admin

Current context:
    Platform

Super Impose:
    Inactive
```

### Super Impose security requirements

Design this so that:

* only authorized platform administrators can initiate it
* the target business must be explicitly selected
* the target business must be validated
* the platform administrator's real identity is retained
* the selected business is temporary context, not permanent membership
* tenant authorization still applies to the selected tenant context
* cross-tenant access cannot accidentally leak through normal tenant queries
* there is a clear "Exit Super Impose" operation
* the UI clearly indicates that Super Impose is active
* the tenant being accessed is always visible
* the platform administrator can return to the platform context
* Super Impose actions can eventually be audited

Please design this through a proper context/service/middleware/authorization abstraction rather than scattering checks throughout controllers and Filament resources.

Do not implement Super Impose as a simple `business_id` overwrite.

---

# 5. Tenant isolation

Tenant isolation is a core requirement.

Normal tenant users must never be able to access another business's:

* branches
* terminals
* users
* products
* categories
* modifiers
* orders
* payments
* printers
* reports
* configuration
* operational data

This must NOT depend only on hiding navigation/menu items.

Enforce the boundary at the appropriate server-side layers:

* policies
* authorization
* query scoping
* services
* Filament resource authorization
* branch access checks

Platform Super Admin is the intentional exception because they operate at platform level and may explicitly Super Impose into a tenant.

Design this cleanly now so future POS modules inherit the same isolation model.

---

# 6. Branch access

Keep the approved model:

```text
Business
    ├── Branch A
    ├── Branch B
    └── Branch C

User
    └── belongs to one Business

branch_user
    ├── User → Branch A
    └── User → Branch C
```

A tenant user may therefore access multiple branches within their own business.

They must never gain branch access merely because they know a branch ID.

Branch authorization must be enforced server-side.

---

# 7. Roles and permissions

Use one global Spatie Permission role/permission system.

Do NOT use Spatie Teams.

Do NOT make roles tenant-specific through a second role system.

The conceptual separation should be:

### Platform roles

Examples:

* Platform Super Admin
* Platform Admin
* Platform Support

### Business roles

Examples:

* Owner
* Manager
* Supervisor
* Cashier
* Waiter
* Kitchen

The exact final role list can remain minimal during Phase 1.

The important architectural rule is:

```text
Role/Permission = WHAT the user may do

business_id = WHICH business they belong to

branch_user = WHICH branches they may access

platform role = WHETHER they operate at platform level
```

Do not mix these responsibilities.

---

# 8. POS staff and operational devices

Use the VectorDine concepts only as future product requirements.

RasaHub should eventually distinguish between:

### Back-office/admin users

Human users accessing the administration system.

### POS terminals

Physical/software POS devices used for sales operations.

A POS terminal is a first-class identity and belongs to a branch.

### KDS screens

Kitchen Display System devices.

These are operational devices but should NOT consume the POS terminal allowance.

### QR ordering

Customer-facing ordering through QR.

This should also NOT be treated as a POS terminal.

Therefore, future subscription limits should be capable of distinguishing:

* branches
* POS terminals
* KDS screens
* back-office users
* QR ordering

Do not collapse all devices/users into one "terminal" concept.

For Phase 1, establish the `terminals` foundation only. Do not implement POS sales yet.

---

# 9. Preserve future VectorDine concepts, but do NOT implement them now

The following are product concepts that RasaHub should be architecturally capable of supporting later.

### Branch/business management

* create branches
* branch assignment
* branch configuration
* potentially clone a branch from another branch

### POS

* terminal identity
* cashier
* tables
* dine-in
* takeaway
* ordering
* order lifecycle
* payments
* receipts

### Products

* products
* categories
* modifiers
* modifier templates
* food/beverage grouping
* pricing
* pricing history

### Printing

* printer groups
* kitchen/food printer
* beverage/bar printer
* cashier/receipt printer
* fixed printer IPs
* receipt configuration

### KDS

* kitchen display
* order routing
* kitchen workflow

### QR ordering

* QR ordering
* customer ordering
* branch/table context

### Offline operation

Eventually RasaHub may need:

* offline-created records
* client-generated identifiers
* idempotency keys
* synchronization
* conflict handling
* journals
* recovery
* hub/device health

### Platform operations

Eventually:

* tenant health
* at-risk tenants
* subscription monitoring
* tenant connection tests
* diagnostics
* setup/runbooks
* offline hub fleet health
* signed authentication
* sync/recovery
* backups
* Super Impose auditing

These are future requirements.

DO NOT implement the actual POS, ordering, payment, printing, KDS, QR, inventory, offline sync, or operational fleet systems in this Phase 1 foundation.

---

# 10. Phase 1 scope

Phase 1 should establish the clean foundation only.

Implement:

* Laravel
* Docker environment
* MySQL
* Filament
* authentication
* platform/business distinction
* businesses
* branches
* terminals
* users
* `users.business_id`
* `branch_user`
* roles
* permissions
* platform authorization
* tenant authorization
* branch authorization
* Super Impose foundation
* basic auditability/foundation for privileged context changes
* tests
* development documentation

Do NOT implement:

* sales
* products
* modifiers
* orders
* payments
* KDS
* printing
* QR ordering
* inventory
* reports
* offline synchronization
* hub fleet management
* subscription billing implementation
* complex diagnostics

Build the foundation so these can be added later without changing the core tenant/access architecture.

---

# 11. Filament structure

RasaHub should clearly distinguish the platform administration experience from the tenant administration experience.

At minimum, design for:

### Platform administration

Platform dashboard should eventually contain:

* businesses/tenants
* tenant status
* subscription/status
* diagnostics
* tenant health
* Super Impose

### Tenant administration

Tenant admin should eventually contain:

* dashboard
* business settings
* branches
* users/staff
* terminals

Later this can expand to:

* products
* categories
* printers
* QR ordering
* reports
* POS configuration

If separate Filament panels are the cleanest architecture, use them.

If you believe a single panel with a strong platform/tenant context system is better, explain the trade-off before choosing.

Do NOT create a complicated panel architecture merely for the sake of complexity.

---

# 12. Data model

Keep the approved core model:

### businesses

* id BIGINT primary key
* ulid unique
* name
* slug unique
* currency CHAR(3)
* timezone
* is_active
* soft deletes

### branches

* id BIGINT primary key
* ulid unique
* business_id foreign key
* name
* code nullable
* timezone
* is_active
* soft deletes

### terminals

* id BIGINT primary key
* ulid unique
* branch_id foreign key
* name
* code
* is_active
* last_seen_at
* soft deletes

### users

Use Laravel's normal users table plus:

* business_id nullable foreign key
* is_active

`business_id = NULL` is allowed for platform-level users.

### branch_user

* branch_id
* user_id
* composite primary key

Use this for branch assignment.

### roles / permissions

Use Spatie Laravel Permission's standard global tables.

No Teams.

Use BIGINT internal primary/foreign keys consistently.

ULIDs can be secondary public identifiers where useful.

Money should eventually use:

`DECIMAL(12,2)`

---

# 13. Docker requirement

Keep the Docker architecture we already approved.

This is a full Docker development environment, not just Docker for MySQL.

All Docker configuration MUST remain under:

```text
rasahub/docker-local/
```

I want to be able to work like this:

```bash
cd ~/projects/rasahub/docker-local
docker compose up -d
```

and when finished:

```bash
docker compose down
```

Required services:

* web / Nginx
* app / PHP-FPM + Laravel
* mysql
* vite / Node

Do not introduce Sail.

Do not add Redis, Mailpit, Elasticsearch, MinIO, Selenium, queue workers, or other infrastructure unless there is an actual Phase 1 requirement.

Keep MySQL data in a named Docker volume so:

```bash
docker compose down
```

does NOT destroy the database.

Do not use:

```bash
docker compose down -v
```

unless intentionally destroying the development database.

Keep the existing host/Homebrew MySQL untouched.

---

# 14. Existing documentation

There are currently architecture/concept documents in the RasaHub project.

Treat those documents as the current RasaHub requirements.

Do not assume they came from VectorDine source code.

If `SYSTEMCONCEPT.md` needs to be moved into `docs/`, preserve its contents exactly and verify before/after hashes.

Do not overwrite or silently rewrite existing requirements.

---

# 15. Important implementation rule

Before making major changes, first inspect the CURRENT RasaHub project.

Do not assume Laravel already exists.

Do not assume VectorDine code exists.

Do not assume database migrations already exist.

Do not assume Filament is already configured.

Determine what is actually present in:

```text
~/projects/rasahub
```

and especially:

```text
~/projects/rasahub/docker-local
```

Then explain the implementation sequence briefly before changing files.

I want a clean, maintainable RasaHub architecture built from scratch.

The goal is NOT to reproduce VectorDine's code.

The goal is to reproduce the useful **product concepts and access architecture** of VectorDine in a new RasaHub codebase, with:

Platform
→ Tenant/Business
→ Branch
→ Operational Devices
→ Users/Staff

and with **Platform Super Impose** designed correctly from the beginning.

Do not proceed into POS feature implementation after establishing this foundation.
