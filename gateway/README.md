# RasaHub print gateway

A small program that runs at a branch and prints RasaHub's kitchen tickets,
receipts, refund slips and shift reports on the branch's network thermal
printers.

A web browser cannot send to a network printer, so RasaHub queues each print
as a **print job**. The gateway collects the branch's waiting jobs every few
seconds, sends each one to its printer in ESC/POS (the command language
almost every thermal receipt printer understands), and tells RasaHub whether
it printed. The admin shows every job under Kitchen → Print log.

It is plain Node.js with no packages to install.

## What you need

- A computer at the branch that stays on during opening hours, on the same
  network as the printers: a small PC, a Raspberry Pi, or the POS computer.
- Node.js 20 or later, or Docker.
- The printers set up in the admin under **Kitchen → Printers**, each with a
  fixed IP address (set one on the printer or reserve it in the router) and
  its raw port, usually 9100.
- The branch's **gateway key**: in the admin, open **Branches**, edit the
  branch and choose **Print gateway key**. It is shown once.

## Running it

```
cd gateway
RASAHUB_URL=https://your-rasahub-address \
RASAHUB_GATEWAY_KEY=the-key-from-the-admin \
npm start
```

or with Docker:

```
docker build -t rasahub-gateway gateway
docker run -d --restart unless-stopped --network host \
  -e RASAHUB_URL=https://your-rasahub-address \
  -e RASAHUB_GATEWAY_KEY=the-key-from-the-admin \
  rasahub-gateway
```

It logs each print, and keeps trying if RasaHub or a printer cannot be
reached.

| Setting | Meaning | Default |
|---|---|---|
| `RASAHUB_URL` | The address of RasaHub | required |
| `RASAHUB_GATEWAY_KEY` | The branch's gateway key | required |
| `POLL_SECONDS` | How often to collect jobs | 3 |
| `OUTPUT` | `printer`, or `file` to write each job as text instead | `printer` |
| `OUTPUT_DIR` | Where `file` output goes, one file per printer | `./prints` |

## Trying it without a printer

Set `OUTPUT=file`. Each job is written, as it would come out of the printer,
to `prints/<printer name>.txt`. In the development setup:

```
cd docker-local
GATEWAY_KEY=the-key-from-the-admin docker compose --profile gateway up gateway
```

and watch `gateway/prints/`.

## How it talks to RasaHub

All requests carry `Authorization: Bearer <gateway key>`.

| Request | What it does |
|---|---|
| `GET /api/gateway/v1/ping` | Checks the key; answers with the business and branch |
| `POST /api/gateway/v1/jobs/claim` | Hands over up to 10 waiting jobs and marks them as printing |
| `POST /api/gateway/v1/jobs/{id}/printed` | Marks a job printed |
| `POST /api/gateway/v1/jobs/{id}/failed` | Marks a job failed, with `{"error": "..."}` |

A job taken but not confirmed within two minutes is offered again, up to
five times. A print job's `document` lists lines (`text`, `row`, `rule`,
`feed`, `cut`) without depending on a printer brand; `src/layout.js` lays
them out for 58 or 80 mm paper.

## Tests

```
npm test
```
