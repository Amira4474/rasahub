# Changelog

What changed in each version of RasaHub, newest first. The current version is
in [`VERSION.md`](../VERSION.md) at the project root, and is shown in the
footer of the platform, tenant admin and POS pages.

Versions are written `vMAJOR.MINOR.PATCH`, for example `v0.0.1`.

## v0.0.1 (2026-10-10)

The first numbered version. It covers the changes made since the platform,
tenant and access-control foundation.

### Added

- **Registration by token, in two steps.** A new tenant opens `/register`,
  enters an invitation token, and only then reaches the registration form at
  `/register/details`. The form asks for the business name, company acronym,
  plan, the owner's name, email address and password. A "registration
  received" page follows.
- **Tokens chosen by the platform user.** Invitations → New invitation has a
  Token field, for example `OCT_RH`: 4 to 32 letters, digits, hyphens or
  underscores, stored in capitals. A random token is suggested. The token
  stays visible in the invitations list and on the invitation's page.
- **Pending activation.** A tenant that registers itself gets a subscription
  on its chosen plan with no dates. Its staff cannot sign in to the admin
  pages or the POS until a platform user activates it.
- **Activate action** on the Subscriptions page, which sets the start and end
  dates of a pending subscription. Recorded in the audit log as
  "Subscription activated".
- **"Awaiting activation" count** on the platform dashboard.
- **Footer with the version** on every platform, tenant admin and POS page:
  copyright on the left; the area name (Platform Console, Business Admin or
  Point of Sale) and the version on the right.
- **`VERSION.md`**, the single place the version is set. The footer reads it
  through `config('app.version')`.
- **"Back to website" button** on the platform and tenant admin sign-in pages.
- **"Register with a token" button** in the website's Get started section.

### Changed

- **Invitations no longer carry an email address or a plan.** The person
  registering enters their own. An optional "Who is it for?" note replaces
  them.
- **One owner email address, one tenant.** Registration is refused for an
  email address that already has any account.
- **The owner is no longer signed in straight after registering**, because
  the subscription has to be activated first.
- **Tokens are stored in readable form** rather than as a hash, so that a
  platform user can look one up. Token attempts are limited to 5 a minute, and
  a wrong, used, expired or revoked token all get the same answer.
- **The Migrations page always reports a result.** "Run central migrations"
  is no longer disabled when nothing is pending, and both buttons say so when
  there is nothing to migrate. "Migrate all tenants" gives one message when
  every tenant is up to date instead of one per tenant.
- **The application runs on port 80** by default, at `http://localhost`,
  instead of port 8080. It is still bound to `127.0.0.1` only.
- **"Extend 1 month" is hidden for a pending subscription**; it is activated
  instead.

### Removed

- **The account (sign-out) card** from the platform and tenant admin
  dashboards. Signing out is in the user menu.
- **"Make a new link"** on invitations. A token is replaced by revoking the
  invitation and making another.
- **Registration links** of the form `/register/<token>`, and the "invitation
  no longer valid" page that went with them.

### Database

- New central migration
  `2026_10_10_090000_register_tenants_by_token_with_pending_subscriptions`:
  - `tenant_invitations`: drops `email`, `plan` and `token_hash`; adds `token`
    (unique) and `note`. Invitations made before this version are revoked,
    because their tokens were never stored.
  - `subscriptions`: `starts_at` and `ends_at` may now be empty.
- No tenant database migrations.

### Upgrading

1. Run the central migration: `php artisan migrate`, or Platform →
   Migrations → Run central migrations.
2. If you kept port 8080 in your own `.env` files, nothing changes for you.
   New copies of the `.env.example` files use port 80.
