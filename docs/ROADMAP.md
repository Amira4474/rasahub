# Roadmap

How RasaHub grows from the platform and access foundation (v0.0.2) into a
working restaurant POS. It records the decisions already made, the order the
modules are built in, and what each module contains.

Each release ends with its changelog entry in [CHANGELOG.md](CHANGELOG.md)
and the version in [`VERSION.md`](../VERSION.md).

## Decisions

| Topic | Decision |
|---|---|
| SST | On for every new tenant, at 6%, with menu prices **including** SST. The tenant changes this in the admin under Restaurant Settings. |
| Rounding | Bill totals are rounded to the nearest 5 sen for **every** payment method, not only cash. A Restaurant Settings switch, on by default. |
| Sizes and options | Modifier groups (Size: Regular / Large). No separate variants table. |
| Business day | A fixed cut-off time per branch, default 04:00. A sale at 01:30 belongs to the previous business day. |
| Printing | Receipts through the browser first. Kitchen and bar tickets through a print gateway later. |
| Customers | A customer list with order history. Loyalty points are kept in view. |
| QR orders | Staff confirm them by default. Automatic confirmation is a branch setting. |
| Money | `DECIMAL(12,2)`, never floating point. |
| Terminal-made records | ULIDs, which double as idempotency keys, so a retried request never creates a second order or payment. |
| Order and payment status | Two separate fields, as `SYSTEMARCHITECTURE.md` requires. |
| History | Orders keep copies of names, prices, tax rate and pricing mode at the time of sale. Later edits never change past orders or reports. |

## Build order

| Release | Module |
|---|---|
| v0.1.0 | Catalogue: categories, products, modifier groups, set meals (done) |
| v0.2.0 | Restaurant Settings (tax, rounding, cut-off, SST number), tables and floor plan |
| v0.3.0 | Sales core: shifts, orders, payments, receipts, customers |
| v0.4.0 | Discounts, voids, cancellations, refunds |
| v0.5.0 | Kitchen: stations, kitchen display, kitchen and bar printers, print gateway |
| v0.6.0 | Reporting |
| v0.7.0 | QR ordering |

**Kept in view** (not scheduled): inventory, offline mode, MyInvois
e-invoicing, loyalty points, online payment for QR orders.

All tables below are in each tenant's own database (`rasahub_<acronym>`).

## v0.1.0 Catalogue (done)

**Categories.** Name, one optional parent (Drinks → Coffee), POS button
colour, order, active. A category that still has products cannot be deleted;
it can be deactivated.

**Products.** Category, name, optional SKU, description, image, price, type
(single item or set meal), order, active.

- `branch_product`: whether the product is available at a branch, and an
  optional branch price. Branch prices are Growth and above.
- `product_price_history`: every change of a base or branch price, with who
  made it and when.

**Modifier groups** are reusable templates, attached to any number of
products. Each has a minimum and maximum number of choices: minimum 1 and
maximum 1 means "choose exactly one" (Size); minimum 0 and maximum 3 means
"up to three" (Add-ons). Each option has a price change, which may be 0 or
negative, and may be the default.

**Set meals** are products of type "set" with choice groups ("Choose a
drink"), each listing the products that may be chosen and any extra charge.
When sold, each part becomes its own line under the set, so the drink goes
to the bar and the food to the kitchen.

**Permissions.** View catalogue; manage catalogue; set availability per
branch.

## v0.2.0 Restaurant Settings and tables

**Restaurant Settings** replaces Business settings in the tenant admin:

| Section | Settings |
|---|---|
| Business details | Name, acronym (read-only), currency, timezone |
| Tax and charges | Charge SST (on), SST rate (6%), prices include SST (yes), SST registration number, round totals to 5 sen (on) |
| Per branch | Service charge (off), business day starts at (04:00) |
| Receipts | Logo, address, footer text |

Only the Owner changes them. A reminder shows while SST is on but no SST
registration number is filled in.

**How SST is worked out** when prices include it, at 6%: a menu price of
RM 10.60 contains RM 0.60 SST (10.60 × 6 ÷ 106). A service charge is
worked out on the amount before SST, and SST is then charged on the service
charge too. Receipts show "SST 6% (included)".

**Tables.** Floor areas per branch (Indoor, Patio), each with tables: name,
seats, shape and position on a floor plan. A table's status comes from its
orders: available, occupied, bill requested. Orders can move between tables
and tables can be merged. Growth and above.

## v0.3.0 Sales core

**Shifts.** A terminal opens a shift with a cash float before taking
payments, and closes it with a cash count. Expected cash, counted cash and
the difference are kept.

**Orders.** Branch, terminal, shift, business date, a number per branch that
restarts each business day, type (dine-in or takeaway), table and guests,
source (POS, waiter, QR), status (draft, pending, confirmed, preparing,
ready, completed, cancelled), payment status (unpaid, partially paid, paid,
partially refunded, refunded), and the amounts: subtotal, discounts, service
charge, SST, rounding and total.

**Order items** keep copies of product and modifier names and prices, the
quantity, a note, and a kitchen status (pending, sent, preparing, ready,
served, voided) for the kitchen display later.

**Payments.** Cash, card, e-wallet or other; card and e-wallet are taken on
an external terminal and recorded with their reference. An order can be
split over several payments.

**Rounding** is applied to the order total before payment, so split payments
and refunds always add up. The difference is stored and printed as
"Rounding".

**Receipts.** A receipt number per branch, separate from the order number,
printed through the browser on 58 or 80 mm paper.

**Customers.** Name, phone (unique), email, birthday, notes and marketing
consent (PDPA). Attached to an order by phone number; the admin shows each
customer's order history and total spend.

**POS screen.** Category tabs, product buttons, modifier and set meal
pickers, the order panel, send to kitchen, pay, print. Built with Livewire
and Alpine, which the project already has.

## v0.4.0 Discounts, voids, cancellations, refunds

- Discounts on an item or the whole order, as an amount or a percentage,
  from presets (Staff 20%) or entered by hand, always with a reason.
- Removing an item before it is sent is free. Voiding a sent item needs a
  supervisor's approval PIN and a reason.
- An unpaid order can be cancelled with a reason.
- Refunds, whole or partial, against a payment, with permission and a
  reason. A cash refund leaves the current shift's drawer.
- Voids, refunds, cancellations, manual discounts and drawer differences are
  written to the audit log.

## v0.5.0 Kitchen

**Stations** (Kitchen, Bar, Dessert). Categories send their products to a
station, and a product can override it.

**Kitchen display.** Screens per branch, each showing chosen stations. Items
move from new to preparing, ready and served, and the POS sees when an order
is ready. Screens refresh every few seconds; no new server software is
needed. Growth and above.

**Printers.** Per branch: name, role (receipt, kitchen, bar), fixed IP
address and port, paper width. Each station has a printer and each terminal
a receipt printer. "Send to kitchen" creates one ticket per station.

**Print jobs** describe what to print without depending on a printer brand,
and record whether each was printed. A small **print gateway** program on the
branch network collects them and sends them to the printers, because a
browser cannot reach a network printer directly. A "Test print" button
checks each printer.

## v0.6.0 Reporting

| Report | Plan |
|---|---|
| Shift report (X during the shift, Z at close) | All |
| Daily sales summary | All |
| Sales by product, category, modifier, hour and staff member | All |
| Discounts, voids and refunds | All |
| SST collected | All |
| Branches compared | Growth and above |
| Consolidated group report | Enterprise |

Reports filter by date range and branch, show staff only their own branches,
and export to CSV.

## v0.7.0 QR ordering

Each table has a printable QR code holding a secure link to that table.
Customers browse the menu on their phone, choose modifiers and set meal
parts, and submit. The order reaches the POS as a pending QR order; staff
confirm it, or the branch confirms it automatically, and it goes to the
kitchen. Customers pay at the counter. Rate-limited against abuse. Growth and
above.

## Permissions to add

| Permission | Owner | Manager | Supervisor | Cashier | Waiter | Kitchen | Inventory |
|---|---|---|---|---|---|---|---|
| View catalogue | ✓ | ✓ | ✓ | | | | ✓ |
| Manage catalogue | ✓ | ✓ | | | | | |
| Set availability per branch | ✓ | ✓ | ✓ | | | | |
| Take orders | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| Take payments, open and close shifts | ✓ | ✓ | ✓ | ✓ | | | |
| Apply preset discounts | ✓ | ✓ | ✓ | ✓ | | | |
| Manual discounts, voids of sent items, cancellations | ✓ | ✓ | ✓ | | | | |
| Refunds | ✓ | ✓ | | | | | |
| View reports (own branches) | ✓ | ✓ | shift only | | | | |
| Manage printers and receipts | ✓ | ✓ | | | | | |
| Kitchen display | ✓ | ✓ | ✓ | | | ✓ | |
