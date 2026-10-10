# Access model

How RasaHub decides who can reach what. Read this before adding a model,
a screen or a permission.

## The hierarchy

```
Platform
└── Business (tenant)
    └── Branch
        └── POS terminal        (KDS screens and QR ordering come later, as
        └── Staff                separate kinds of device, not as terminals)
```

Four separate questions are answered by four separate things. They are never
mixed.

| Question | Answered by |
|---|---|
| Does this person run the platform, or work in a business? | `users.business_id` is `NULL` for a platform user |
| Which business? | `users.business_id` |
| Which branches? | `branch_user` in the tenant's database, or the `branches.access-all` permission |
| What may they do? | Roles and permissions |

## Where the data lives

There is one **central database** and one **database per tenant**, all on the
same MySQL server.

| Database | Holds |
|---|---|
| Central (`rasahub`) | Businesses, users, roles and permissions, subscriptions, invitations, the audit log, sessions |
| Tenant (`rasahub_<acronym>`, for example `rasahub_dc`) | That business's branches, POS terminals and branch assignments, and everything later built on them (menus, orders, payments) |

Users stay in the central database, in one `users` table, because a platform
user belongs to no tenant and every sign-in page has to find the account
before it knows which tenant is involved.

A tenant's database is created the moment the business is, by the `Business`
model itself, and built from the migrations in `database/migrations/tenant`.
It is never dropped automatically: deleting a tenant in the platform is a
soft delete, and a business cannot be permanently deleted while its database
exists.

IDs in a tenant database start from 1 for every tenant, so a branch ID is
only meaningful together with its business. Use the `ulid` column where a
globally unique identifier is needed.

## Three ways in

Each has its own sign-in page. A correct password at the wrong door gets the
same "credentials do not match" message as a wrong password.

| Door | Sign-in page | For | Built with |
|---|---|---|---|
| Platform | `/platform/login` | Platform users | Filament panel `platform` |
| Tenant admin | `/admin/login` | Owner, Manager, Supervisor | Filament panel `admin` |
| Staff POS | `/pos/login` | Owner, Manager, Supervisor, Cashier, Waiter | Blade pages |

The POS sign-in has two steps: the company acronym (`businesses.acronym`),
then email and password. The second step only accepts an active account that
belongs to that company and holds `pos.access`.

Nobody in an inactive business can sign in anywhere, and neither can the
staff of a business whose subscription is suspended or has ended (see
Subscriptions below).

All three doors use the same `users` table and the same session. What keeps
them apart is `User::canAccessPanel()` and `User::canAccessPos()`, which are
checked on every request, not only at sign-in.

## Roles and permissions

There is one global set, defined in `app/Enums/Role.php` and
`app/Enums/Permission.php`. Run `php artisan db:seed` after changing either.

- Every permission is either a **platform** permission or a **tenant**
  permission (`Permission::isPlatform()`).
- Every role is either a **platform** role or a **business** role
  (`Role::isPlatform()`), and only holds permissions of its own kind.
- A platform user can only be given platform roles, and a tenant user only
  business roles. `User::assignRole()` refuses anything else, and the forms
  only offer the right kind.

| Platform role | Can |
|---|---|
| Platform Super Admin | Everything on the platform, including managing platform users and running migrations |
| Platform Admin | Manage tenants, subscriptions and invitations, Super Impose, read the audit log |
| Platform Support | View tenants and their subscriptions |

Every permission check is answered in one place, the `Gate::before` rule in
`AppServiceProvider`:

1. A tenant user never passes a platform permission.
2. A platform user never passes a tenant permission, except during Super Impose.
3. Otherwise the user's roles decide.

There is no role that "passes everything".

## Tenant context

`App\Tenancy\TenantContext` answers one question for the whole application:
**which business is this request acting in?**

| Who is signed in | Acting in |
|---|---|
| A tenant user | Their own business |
| A platform user | No business |
| A platform user in Super Impose | The business they entered |
| Nobody (console, queued jobs) | No business, until the code chooses one |

Scopes, policies and forms read the answer from `TenantContext`. Nothing else
should work it out from `auth()->user()->business_id`.

`App\Tenancy\TenantDatabase` turns that answer into a database: the `tenant`
connection is pointed at the current business's database, and is closed
whenever who is signed in, or which business they are in, changes.

## Tenant isolation

There are two kinds of tenant-owned model, and each is safe by default.

**Models in the tenant's database** (`Branch`, `Terminal`, and every model
added for menus, orders and so on) use the `App\Tenancy\UsesTenantDatabase`
trait:

- Every query goes to the database of the business being acted in. Another
  tenant's rows are not in that database at all.
- With no business being acted in, there is no database to read:
  the query throws `NoTenantException` rather than returning anything.
- A record loaded from one tenant cannot be read from, saved to or deleted
  from another tenant's database: that throws `CrossTenantWriteException`.
- A query built inside one business cannot be run after leaving it.

```php
Branch::all();   // only the current business's branches
```

**When adding a model for tenant data, add `UsesTenantDatabase` and put its
migration in `database/migrations/tenant`.** Do not give it a `business_id`.

**Models in the central database that belong to a business** (today only
`User`) use the `App\Tenancy\BelongsToBusiness` trait, which limits reads to
the current business with a global scope, fills in `business_id` on create,
and refuses writes into another business. A platform user outside Super
Impose gets no rows. Platform code that must read across businesses says so:

```php
User::acrossBusinesses()->whereNotNull('business_id')->count();
```

Platform or system code can act in one chosen business:

```php
$business->run(fn () => Branch::count());
```

On top of the business boundary, `Branch::visibleTo($user)` and
`Terminal::visibleTo($user)` apply the branch assignment, and the policies
check both. Hiding a menu item is never the protection.

A relationship cannot cross from a tenant database to the central one in a
single query. `User::branches()` works, because the branches and the
assignment table are both in the tenant's database. There is deliberately no
`Branch::users()`.

## Super Impose

Lets a platform user work inside one tenant's admin pages with full access,
without becoming a tenant user. `App\Tenancy\SuperImpose` holds it.

```
Platform panel → Tenants → "Super Impose" on a tenant's row or page
    → tenant admin pages, with a red banner naming the tenant
    → "Exit Super Impose" → back to the platform panel
```

What it does and does not do:

- The platform user stays signed in **as themselves**. `users.business_id`
  is never changed and no tenant membership is created.
- The only change is a note in their session naming the business entered.
  `TenantContext` then reports that business as the current one.
- Inside, they hold every tenant permission, but only in that one business.
  Scopes and policies keep every other tenant out of reach.
- It has **no time limit**. It ends on "Exit Super Impose" or on sign-out.
- It ends immediately if the user loses the `super-impose.start` permission.
- While it is active the platform panel redirects to the tenant panel, so the
  two contexts are never on screen together.
- It gives access to the tenant admin pages only, not to the POS.
- It works for a tenant whose subscription is suspended or has ended, so
  that the tenant can still be helped.
- Only Platform Super Admin and Platform Admin may start it.

## Audit log

`audit_logs` records privileged actions: who, what, in which business,
whether it happened under Super Impose, from which IP address, and when.
Entries are written through `App\Audit\Audit` and are never edited.

Recorded today: Super Impose started and exited (including by sign-out),
platform user sign-ins, changes to a user's roles, subscription changes
(activated, edited, suspended, resumed, extended), invitations (created,
revoked), tenant registrations, and migrations run from the platform. The log
is read at `/platform/audit-log`.

To record something new, add a case to `App\Enums\AuditEvent` and call:

```php
app(Audit::class)->record(AuditEvent::Something, $business, ['detail' => '…']);
```

## Devices

`terminals` holds **POS terminals only**. Kitchen display screens and QR
ordering will get their own tables, so that future subscription limits can
count branches, POS terminals, KDS screens, back-office users and QR ordering
separately.

## The platform panel

| Page | What it is for | Who |
|---|---|---|
| Tenants | Every business. A tenant's page shows its business information, subscription, database name and counts of branches, POS terminals and staff, and is where Super Impose starts. | View: all platform roles. Create and edit: Super Admin, Admin |
| Subscriptions | Each tenant's plan, status, start date and end date. Edit, suspend or resume, extend by one month. | View: all platform roles. Change: Super Admin, Admin |
| Plans | Compare the limits and features of each plan. | Super Admin, Admin, Support |
| Invitations | Invite someone to register a new tenant. | Super Admin, Admin |
| Platform users | The people who run the platform. | Super Admin |
| Audit log | Read-only trail of privileged actions. | Super Admin, Admin |
| Migrations | Bring the central database and the tenant databases up to date. | Super Admin |

## Subscriptions

Every business has exactly one subscription (`subscriptions`, central
database): a plan, a start date and an end date. A tenant that registered
itself has a plan but no dates until a platform user activates it. The plans are the three on
the website (`App\Enums\Plan`). No payment is taken; this records what a
tenant is entitled to.

| Status | When | Tenant's own staff |
|---|---|---|
| Pending activation | Registered, no dates set yet | Cannot sign in |
| Active | Not suspended, and today is on or before the end date | Can sign in |
| Expired | Today is after the end date | Cannot sign in |
| Suspended | Suspended in the platform, whatever the dates | Cannot sign in |

A blocked tenant's staff cannot sign in to the admin pages or the POS, and
anyone already signed in is signed out on their next request. Once they have
entered a correct password they are told why. Everyone else still gets the
usual "credentials do not match" message.

"Activate" gives a pending subscription its start and end dates, which lets
the tenant's staff sign in. "Extend 1 month" adds a month to the end date of
a running subscription, or gives an ended one a month from today.

## Plans

What each plan allows is defined in `config/plans.php` and shown in the
platform panel under **Plans** (every platform role may read it).

| | Starter | Growth | Enterprise |
|---|---|---|---|
| Branches | 1 | 5 | Unlimited |
| POS terminals | 2 | Unlimited | Unlimited |
| Staff accounts | 5 | Unlimited | Unlimited |
| Support | Email | Priority | Dedicated account manager |

The limits are enforced in the tenant admin pages. Once a business has as
many branches, POS terminals or staff accounts as its plan allows, the "New"
button goes, the list page says why, and the create page refuses to open or
save. This applies to every role and to a platform user in Super Impose.
Nothing a tenant already has is removed when it moves to a smaller plan; it
just cannot add more.

The page also lists the features of each plan. Only two are built so far
(roles and branch assignment for staff, and more than one branch); the rest
are marked "Not built yet" and say what the plan will include. Use
`Plan::includes('kitchen_display')` to gate a feature when it is built.

## Invitations and registration

Nobody can register without an invitation. A tenant comes into being in one
of two ways.

**By invitation.** Platform panel → Invitations → New invitation: a token,
an optional note of who it is for, and an expiry (7 days by default). The
platform user chooses the token, for example `OCT_RH` (4 to 32 letters,
digits, hyphens or underscores, stored in capitals); a random one is
suggested. Each token is used for one invitation only. It stays visible in
the platform so that it can be given to the person invited.

The person invited opens the public page `/register` and enters the token.
If it is valid they are taken to `/register/details`, where they enter a
business name, a plan, their name, their email address and a password. That
creates the business, its database, their Owner account and
a subscription on the chosen plan with no dates, and uses the token up. They
cannot sign in yet: a platform user activates the subscription under
Subscriptions, and sets its dates from then on.

**The company acronym is assigned by RasaHub**, from the first letter of each
word of the business name: "Demo Cafe" becomes `DC`. A name of one word gives
its first three letters. If another tenant already has the acronym, including
a retired one, a number is added: `DC2`, `DC3`. The acronym is what staff type
at the POS sign-in, and the tenant's database is named after it
(`rasahub_dc`), so it never changes. The new Owner is shown it after
registering, and it is under Business settings.

A token works once, until it expires or is revoked. An email address can own
only one tenant: registration is refused for an address that already has an
account. Token attempts are limited to 5 a minute, and the page gives the same
answer for a token that is wrong, used, expired or revoked. A short or
obvious token is still easier to guess than the suggested one.

**By the platform.** Platform panel → Tenants → New tenant, then Super
Impose into it and add the Owner under Staff.

## Migrations

| | Migrations in | Run with | Or in the platform |
|---|---|---|---|
| Central database | `database/migrations` | `php artisan migrate` | Migrations → Run central migrations |
| Every tenant database | `database/migrations/tenant` | `php artisan tenants:migrate` | Migrations → Migrate all tenants |
| One tenant database | | `php artisan tenants:migrate KKB` | Migrations → Migrate, on that tenant's row |

After an update that adds migrations, run the central ones first and then the
tenants'. A tenant created afterwards starts fully migrated. The Migrations
page shows what is pending for the central database and for each tenant.

## Setting up a new tenant

Send an invitation (see above), or:

1. Platform panel → Tenants → New tenant. Choose its subscription, and an
   acronym or leave that empty to have one assigned from the name.
2. Super Impose into it.
3. Staff → New staff member: create the Owner.
4. Exit Super Impose. The Owner signs in at `/admin/login`.
