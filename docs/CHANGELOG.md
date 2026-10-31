# Changelog

What changed in each version of RasaHub, newest first. The current version is
in [`VERSION.md`](../VERSION.md) at the project root, and is shown in the
footer of the website and of the platform, tenant admin and POS pages.

Versions are written `vMAJOR.MINOR.PATCH`, for example `v0.0.1`.

## v0.6.0 (2026-11-03)

Reporting: twelve reports with Excel and PDF downloads, a sales dashboard
and the X report.

### Added

- **Reports** in the tenant admin, on a full-width page: the report is
  chosen from a list above it, for a range of business days and a branch
  (or all the user's branches), with quick ranges (today,
  yesterday, this week, this month, last month), headline figures, a chart
  where it helps, and a table with totals:
  - **Sales summary**: orders, guests, gross sales, discounts, service
    charge, SST, rounding, takings, refunds and net takings, day by day.
  - **Sales by product**, **by category** and **modifiers** (times chosen
    and what each option added).
  - **Sales by hour**, in each branch's own time.
  - **Sales by staff**: orders and takings, discounts given, lines voided
    and refunds given.
  - **Payments** by method, with cash refunds, for reconciling.
  - **Discounts, voids and refunds**, each with who did it, who approved it
    and why.
  - **SST** collected and the taxable amount, day by day.
  - **Shifts**: float, takings, expected and counted cash, difference.
  - **Branches compared** (Growth and Enterprise) and the **Group report**
    (Enterprise).
- **Downloads as Excel and PDF.** The .xlsx files are written by RasaHub
  itself, with real numbers Excel can add up, frozen headings and totals;
  the PDFs are made with dompdf, landscape for wide reports.
- **Dashboard**: net takings, orders and average spend today against the
  same day last week, the last 30 days' net takings, and today's best
  sellers.
- **X report** on the POS (shift pop-up → Print X report): the shift's
  figures so far without closing it, at the receipt printer or from the
  browser. The closing report is now headed "Shift report (Z)".
- **Permissions** `reports.view` (Owner, Manager) and `reports.shifts`
  (Owner, Manager, Supervisor).

### How the figures are worked out

- Sales are paid orders only, by the business day they belong to, using
  the names, prices and tax each order kept. Cancelled and open orders are
  left out.
- Refunds count on the business day they were given, as in the cash drawer.
- "Today", the quick ranges and the dashboard use the branch's own business
  day (its timezone and the time its day starts), not the server's calendar
  day, which runs in UTC.
- Owners see every branch, Managers their own branches, Supervisors shift
  reports only.

### Changed

- **Scrollbars are hidden** everywhere (website, POS, tenant admin and
  platform panels). Scrolling still works with the mouse wheel, trackpad,
  touch and keyboard.
- **Plans page**: "Sales reports, with Excel and PDF downloads",
  "Branches compared" and "Consolidated group report" are marked as built.
- **Security updates**: Livewire 4.4.8 (an authorization fix) and Symfony
  8.1.10 components (including symfony/mime).

### Packages

- Adds `barryvdh/laravel-dompdf` (with `dompdf/dompdf`) for PDF reports.

### Upgrading

1. `composer install`, then sync roles and permissions: `php artisan db:seed`.
2. No database changes.

## v0.5.0 (2026-11-03)

The kitchen: stations, kitchen screens, network printers and the print
gateway. A test suite four times faster.

### Added

- **Stations** (Admin → Kitchen → Stations), such as Kitchen, Bar and
  Dessert. Each category sends its products to a station, and a product can
  choose another. Order lines keep the station they were sent to.
- **Kitchen screens** (Growth and Enterprise): a device of its own per
  branch, showing chosen stations. Kitchen staff sign in to the POS on the
  device and choose it at the device step. The screen shows each order with
  a running timer that turns amber and then red (10 and 15 minutes by
  default, set per branch), lines tapped ready one at a time or with "All
  ready", a Ready column with "Served", "Recently served" to recall a bump
  made by mistake, and an optional sound for new orders. It refreshes every
  5 seconds.
- **Printers** (Admin → Kitchen → Printers): network thermal printers per
  branch, with role (receipts, kitchen or bar tickets), fixed IP address and
  port, paper width (58 or 80 mm), the stations whose tickets they print,
  and a Test print button. Each POS terminal can have a receipt printer.
- **Printing**: Send to kitchen prints one ticket per station (order
  number, table, time, items, options and notes, in large type); a paid
  takeaway's tickets print when it is paid; a void prints a VOID ticket at
  its station; receipts, refund slips and the end-of-shift report print at
  the terminal's receipt printer. Without a printer, receipts still print
  from the browser and the kitchen works from its screens.
- **Print log** (Admin → Kitchen → Print log): every print job, its status
  and error, with Reprint.
- **Print gateway** (`gateway/`): a small Node.js program with no packages,
  run at each branch, that collects the branch's print jobs every few
  seconds, sends them to the printers in ESC/POS, and reports back. It can
  write each job to a text file instead, for trying it without a printer
  (`docker compose --profile gateway up gateway` in development). It signs
  in with the branch's gateway key (Admin → Branches → Print gateway key,
  shown once). Jobs not confirmed within two minutes are offered again, up
  to five times.
- **Station filter** on the POS kitchen display tab.
- **Permission** `kitchen.manage` for the kitchen set-up pages (Owner,
  Manager).

### Changed

- **The test suite runs in about 4 minutes instead of 15.** Test tenant
  databases are kept and emptied for the next test instead of being dropped
  and built again, and test businesses get acronyms in sequence (TA, TB, …)
  so each test reuses the last one's databases. Real tenants are untouched.
- **The POS device step** lists kitchen screens as well as POS terminals.
- **The platform panel's menu is grouped** like the tenant admin's: Tenants
  (Tenants, Invitations), Subscriptions (Subscriptions, Plans) and
  Administration (Platform users, Audit log, Migrations).
- **Plans page**: "Kitchen and bar tickets on network printers" is on every
  plan; "Kitchen display" is marked as built.

### Database

- Tenant migration `2026_11_03_100000` adds `stations`, `printers`,
  `printer_station`, `kitchen_screens` and `print_jobs`; a station on
  categories, products and order lines; a receipt printer on terminals; and
  kitchen timer and gateway key columns on branches.

### Upgrading

1. Sync roles and permissions: `php artisan db:seed`.
2. Migrate every tenant: `php artisan tenants:migrate`.
3. To print on network printers: add the printers, make the branch's
   gateway key, and run the gateway at the branch (see `gateway/README.md`).

## v0.4.0 (2026-11-02)

Correcting orders: discounts, voids, cancellations and refunds, with a
supervisor's PIN where needed.

### Added

- **Discounts** on a line or on the whole order:
  - **Presets** given with one tap, set up under Admin → Sales →
    Discounts (a percentage or an amount, for one item or the order).
  - **Typed discounts**, as a percentage or an amount, always with a
    reason; no cap.
  - Discounts come off before SST and service charge, follow the order as
    it changes, and show on the order panel, the receipt and the admin.
    One that would take the total below what is already paid is refused.
- **Voids**: a line already sent to the kitchen can be voided with a
  reason. It stays on the order crossed out and counts for nothing; the
  kitchen display shows it struck through as "Void, do not make".
- **Cancelling an order** with nothing paid, with a reason. The table is
  freed.
- **Refunds** of a paid order, for chosen items and quantities (each at its
  share of what was paid, with SST, service charge and discounts) or for an
  amount. Always in cash from the current shift's drawer, within 30 days of
  payment (Restaurant settings → Refunds allowed for). A refund slip is
  printed with its own number (BGR-RF000001) and a line for the customer's
  signature. The order becomes Partly refunded or Refunded, and a line
  cannot be refunded twice.
- **Supervisor approval at the till**: when the person at the till may not
  make a correction alone, the dialog asks for a Supervisor's, Manager's or
  Owner's **approval PIN** (4 to 6 digits, set on the staff record, stored
  hashed and unique in the business). Five wrong PINs pause approvals for a
  minute.
- **Audit log** entries for every typed discount, void, cancellation,
  refund and cash drawer difference, with who approved it and why.
- **Admin**: the order page shows discounts, voided lines, refunds and
  cancellation with who approved them.
- **Permissions** `discounts.apply`, `discounts.manual`,
  `discounts.manage`, `items.void`, `orders.cancel` and `refunds.issue`.

### Changed

- **One Pay button** on the POS order panel, showing the amount to pay,
  replaces the row of payment method buttons, so the item list keeps its
  room. It opens a pop-up with the bill (subtotal, discounts, service
  charge, SST, rounding, payments so far and what is left to pay) and the
  payment methods; choosing one shows its amount, reference or QR code.
  Send, Discount and Cancel share one row above it.
- **Cash in the drawer** now subtracts cash refunded during the shift.
- **Receipts** show discounts and refunds, and leave voided lines out.

### Database

- Central migration `2026_11_02_100000` adds `users.approval_pin`.
- Tenant migration `2026_11_02_100000` adds `discount_presets`,
  `order_discounts` and `refunds`, discount, refund and cancellation
  columns on `orders`, discount, refund and void columns on
  `order_items`, and `restaurant_settings.refund_days`.

### Upgrading

1. Run the central migration: `php artisan migrate`.
2. Sync roles and permissions: `php artisan db:seed`.
3. Migrate every tenant: `php artisan tenants:migrate`.
4. Give each Supervisor, Manager and Owner an approval PIN on their staff
   record.

## v0.3.0 (2026-11-01)

The sales core: RasaHub takes orders and payments.

### Added

- **POS screen** (Livewire), replacing the placeholder page:
  - **Tables** tab: the branch's floor plans, each table green when free,
    red when occupied and amber when partly paid. Tapping a free table
    starts a dine-in order with its number of guests; tapping an occupied
    one opens its order.
  - **Menu** tab: category tabs and product buttons in each category's
    colour, with branch prices, and sold-out or inactive items left out.
    Products with modifiers open a picker that enforces "Choose 1" and "Up
    to 3"; set meals open a picker for each choice. A kitchen note and a
    quantity can be added.
  - **Open orders** tab, to pick up any open order at the branch.
  - **The order panel**: lines with their modifiers, set meal parts and
    notes; quantity and removal until a line is sent; customer; subtotal,
    service charge, SST, rounding, total, payments and balance.
  - **Send to kitchen**, **move or merge table**, and **add customer** by
    phone number with an optional name.
  - **Payment** by any of the restaurant's methods, split across several if
    wanted. Cash shows quick note buttons and the change. A QR method shows
    the restaurant's QR code on screen for the customer to scan. Paying in
    full completes the order and opens the receipt.
  - **Shifts**: one shift button in the header opens a pop-up. With a
    shift open it shows who opened it and when, the opening float, the cash
    that should be in the drawer now, what was taken by each payment method,
    the orders paid and the open orders still unpaid, with **Close shift**
    (asks for the cash count and reports whether the drawer is exact, over
    or short) and **Switch terminal**. With no shift, it asks for the float.
  - **Fits the window**: the menu, tables and order panel are all on one
    screen without scrolling the page; each panel scrolls on its own.
  - **Product photos** on the menu buttons, or the category colour and the
    product's initial when there is no photo.
  - **Header menu** with three sections: **Sell**, **Transactions** and
    **Kitchen display**, and a running clock in the branch's time.
  - **Transactions**: the branch's orders on any business day, with a
    search by order number, customer name or phone, the day's paid total,
    and each order opening in the order panel with its receipt.
  - **Kitchen display** (Growth and Enterprise): orders in the kitchen in
    three columns, New, Preparing and Ready, each moved on with one tap
    until served. It refreshes every 5 seconds. Kitchen staff sign in to
    the POS and start here; they take no orders.
  - **Alerts** in the centre of the screen with an icon, naming the order.
    Good news closes by itself after a few seconds; problems wait for OK.
- **Terminal choice** as the third step of the POS sign-in, at every
  sign-in, with "Switch terminal" on the screen.
- **Orders** with a number per branch that restarts each business day and
  includes the branch code (BGR-0042), dated by the branch's cut-off time.
  Order status and payment status are kept apart. Every order keeps the tax
  settings, names and prices it was sold under.
- **Totals**: SST on the whole bill (included in or added to prices), the
  branch's service charge on the amount before SST with SST on it too, and
  rounding of the total to 5 sen for every payment method.
- **Receipts** laid out for 80 mm thermal printers and printed through the
  browser: logo, header, branch address and phone, SST number, items,
  totals, payments, change and footer. A receipt number per branch
  (BGR-R000001). Reprinted from the POS or from the order in the admin.
- **Admin → Sales**: Orders (filters by branch, status, type and business
  day; order detail; print receipt), Shifts (float, expected, counted and
  difference), Customers (visits, total spent, last visit, marketing
  consent with the date given, order history) and Payment methods
  (defaults Cash, Card, QR payment and Other; upload a QR code image; ask
  for a reference; reorder).
- **Super Impose into the POS.** On the platform's tenant list and tenant
  page, "Super Impose" now offers **Tenant admin** or **POS**. The POS way
  asks for a terminal and opens the tenant's POS screen with the red banner
  and an "Exit Super Impose" button; each banner links to the other side.
  The audit log records which way was used.
- **Exit Super Impose returns to the tenant's page** in the platform panel,
  instead of the platform dashboard.
- **Permissions** `orders.take`, `payments.take`, `shifts.manage`,
  `orders.view`, `customers.view` and `customers.manage`. Cashiers open
  shifts and take orders and payments; Waiters take orders only.
- **Payments and orders are safe to retry**: each payment carries a key,
  so a double tap records it once, and the order is locked while a payment
  is taken.

### Changed

- **Plans page**: "POS for dine-in and takeaway" and the new "Customers
  and order history" are marked as built.

### Database

- Tenant migrations `2026_11_01_100000` to `2026_11_01_100002` add
  `payment_methods`, `customers`, `shifts`, `sequences`, `orders`,
  `order_items`, `order_item_modifiers` and `payments`.

### Upgrading

1. Sync roles and permissions: `php artisan db:seed`.
2. Migrate every tenant: `php artisan tenants:migrate`, or Platform →
   Migrations → Migrate all tenants.

## v0.2.0 (2026-10-31)

Restaurant Settings with SST, rounding and receipts; branch service charge
and business day; floor plans and tables. A faster test suite.

### Added

- **Restaurant settings** page in the tenant admin, replacing Business
  settings (the old address redirects). Sections:
  - **Business details**: name, acronym (read-only), currency, timezone.
  - **Tax and charges**: charge SST (on), SST rate (6%), menu prices
    include SST (on), SST registration number, round bill totals to the
    nearest 5 sen for every payment method (on). A worked example shows how
    SST is counted at the chosen rate.
  - **Receipts**: logo, header text and footer text.
- **New tenants start with SST on at 6%, prices including SST, and
  rounding on.**
- **SST number reminder**: while SST is charged but no registration number
  is entered, the Owner sees a reminder on the dashboard and on the settings
  page.
- **Per-branch settings** on the branch form: address and phone for
  receipts, service charge (0 for none, dine-in only by default), and the
  time the business day starts (default 04:00). A sale at 01:30 counts
  towards the previous day.
- **Floor plans**, on every plan: floor areas per branch, such as
  Indoor or Patio, each with tables (name, seats, shape). A floor plan page
  shows the tables as they stand in the room; managers drag them into place
  and save. Each user only sees the areas of their branches. Table status
  (occupied, bill requested) arrives with orders in v0.3.0.
- **Permissions** `tables.view` (Owner, Manager, Supervisor) and
  `tables.manage` (Owner, Manager).
- **SST arithmetic** (`App\Support\Sst`) on decimal strings, ready for
  the sales core.

### Changed

- **Plans page**: "Table management" becomes "Tables and floor plans", is
  included in every plan including Starter, and is marked as built. The
  website's Growth plan now lists "Kitchen display" on its own.
- **Tenant admin navigation**: Restaurant settings, Branches, Floor plans,
  POS terminals, Staff, then the Menu group.
- **The test suite runs in about 5 minutes instead of 11.** The first test
  tenant database is built by its migrations; later ones are created from a
  copy of its table definitions (`App\Tenancy\TenantSchemaCopy`, switched
  on only by `TENANT_SCHEMA_COPY` in `phpunit.xml`). Real tenants are always
  built by their migrations.

### Database

- Tenant migrations `2026_10_31_100000` to `2026_10_31_100002` add
  `restaurant_settings`, add receipt, service charge and business day
  columns to `branches`, and add `floor_areas` and `dining_tables`.

### Upgrading

1. Sync roles and permissions: `php artisan db:seed`.
2. Migrate every tenant: `php artisan tenants:migrate`, or Platform →
   Migrations → Migrate all tenants.

## v0.1.0 (2026-10-30)

The menu: categories, products, modifier groups and set meals, the first
release of the plan in [ROADMAP.md](ROADMAP.md).

### Added

- **Menu section in the tenant admin**, with Categories, Products and
  Modifiers.
- **Categories**, with one optional level of nesting (Drinks › Coffee), a
  POS button colour, drag-and-drop ordering and an active switch. A category
  that still has products or sub-categories cannot be deleted.
- **Products**: category, name, optional SKU, description, photo, price
  (including SST), active switch and drag-and-drop ordering. Photos are kept
  per tenant under `storage/app/public/tenants/<acronym>/products`.
- **Modifier groups**, reusable across products: a minimum and maximum
  number of choices ("Choose 1", "Optional, up to 3"), and options with a
  price change, a default and an active switch. A group cannot start with
  more options chosen than it allows.
- **Set meals**: a product of type "Set meal" with choices such as "Choose a
  drink", each listing single items that may be chosen and any extra charge.
  A set cannot contain another set.
- **Products per branch**: a product can be marked sold out at a branch.
  On Growth and Enterprise a branch can also have its own price. Each user
  only sees the branches they work in.
- **Price history** on each product: every change of its own price or a
  branch price, with who made it and when.
- **Permissions** `catalogue.view`, `catalogue.manage` and
  `catalogue.availability`. Owners and Managers manage the menu; Supervisors
  view it and set availability at their branches; the Inventory role can now
  sign in to the admin to view the menu.
- **`docs/ROADMAP.md`**: the decisions made and the plan for the releases up
  to QR ordering.

### Changed

- **Plans page**: "Menus, categories and modifiers" and the new "Set meals"
  and "Per-branch prices" are marked as built. "Per-branch pricing and
  reports" is split into "Per-branch prices" and "Per-branch reports".

### Database

- Tenant migrations `2026_10_30_100000` to `2026_10_30_100002` add
  `categories`, `modifier_groups`, `modifiers`, `products`,
  `modifier_group_product`, `branch_product`, `product_price_changes`,
  `set_groups` and `set_group_items` to every tenant database. Money is
  `DECIMAL(12,2)`.

### Upgrading

1. Sync roles and permissions: `php artisan db:seed`.
2. Migrate every tenant: `php artisan tenants:migrate`, or Platform →
   Migrations → Migrate all tenants.
3. Link public storage once, for product photos:
   `php artisan storage:link`.

## v0.0.2 (2026-10-10)

Plans with enforced limits, and company acronyms assigned by RasaHub.

### Added

- **The version in the website footer**, next to the platform sign-in icon.
- **Plans page in the platform panel**, comparing the limits, features and
  support of Starter, Growth and Enterprise, with the number of tenants on
  each. The plans are defined in `config/plans.php`.
- **Plan limits are enforced.** Starter allows 1 branch, 2 POS terminals and
  5 staff accounts; Growth allows 5 branches; Enterprise is unlimited. At the
  limit a tenant cannot add more, whatever the user's role, including under
  Super Impose.

### Changed

- **The company acronym is assigned by RasaHub.** The registration form no
  longer asks for it. It is the first letter of each word of the business
  name ("Demo Cafe" becomes `DC`), or the first three letters of a one-word
  name. If the acronym is taken, a number is added (`DC2`). The new Owner is
  shown the acronym after registering.
- **Tenant databases are named after the acronym**: `rasahub_dc` instead of
  `rasahub_tenant_1`.
- **In the platform's New tenant form the acronym is optional** (empty means
  assigned from the name) and can no longer be edited afterwards.
- **The acronym `TESTING` is reserved**, because `rasahub_testing` is the test
  suite's database.

### Database

- New central migration
  `2026_10_10_100000_name_tenant_databases_after_acronyms` moves each existing
  tenant's tables from `rasahub_tenant_<id>` into `rasahub_<acronym>` and
  removes the old database.
- The application's MySQL user now needs rights on `rasahub\_%` rather than
  `rasahub\_tenant\_%`.

### Upgrading

1. Give the MySQL user its new rights, once:
   `docker compose exec mysql sh /docker-entrypoint-initdb.d/02-tenant-databases.sh`
2. Run the central migration: `php artisan migrate`.

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
