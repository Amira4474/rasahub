// RasaHub print gateway.
//
// Runs on a computer in one branch, on the same network as its printers.
// Every few seconds it asks RasaHub for the branch's waiting print jobs,
// sends each to its printer, and reports whether it printed.
//
// Settings, from the environment:
//   RASAHUB_URL           the address of RasaHub, e.g. https://pos.example.com
//   RASAHUB_GATEWAY_KEY   the branch's gateway key, from the admin (Branches)
//   POLL_SECONDS          how often to ask (default 3)
//   OUTPUT                "printer" (default) or "file", to write each job
//                         as text into OUTPUT_DIR instead, for trying it out
//   OUTPUT_DIR            where "file" output goes (default ./prints)
import { mkdir, appendFile } from 'node:fs/promises';
import net from 'node:net';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { toEscPos } from './escpos.js';
import { asText } from './layout.js';

const config = {
  url: (process.env.RASAHUB_URL ?? '').replace(/\/+$/, ''),
  key: process.env.RASAHUB_GATEWAY_KEY ?? '',
  pollSeconds: Math.max(1, Number(process.env.POLL_SECONDS ?? 3)),
  output: process.env.OUTPUT === 'file' ? 'file' : 'printer',
  outputDir: process.env.OUTPUT_DIR ?? './prints',
};

function log(message) {
  console.log(`${new Date().toISOString()} ${message}`);
}

async function call(method, route, body) {
  const response = await fetch(`${config.url}/api/gateway/v1/${route}`, {
    method,
    headers: {
      Authorization: `Bearer ${config.key}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: body ? JSON.stringify(body) : undefined,
    signal: AbortSignal.timeout(15000),
  });

  if (!response.ok) {
    throw new Error(`${route}: ${response.status} ${await response.text()}`);
  }

  return response.json();
}

// Send raw bytes to a network printer on its raw port (usually 9100).
export function sendToPrinter(host, port, bytes, timeoutMs = 10000) {
  return new Promise((resolve, reject) => {
    const socket = net.createConnection({ host, port }, () => socket.end(bytes));
    socket.setTimeout(timeoutMs, () => socket.destroy(new Error(`no answer from ${host}:${port}`)));
    socket.on('error', reject);
    socket.on('close', (hadError) => (hadError ? null : resolve()));
  });
}

async function print(job) {
  const { printer, document } = job;

  if (config.output === 'file') {
    await mkdir(config.outputDir, { recursive: true });
    const file = path.join(config.outputDir, `${printer.name.replace(/[^\w-]+/g, '_')}.txt`);
    await appendFile(file, `${asText(document, printer.paper_width)}\n\n`);
    return;
  }

  await sendToPrinter(printer.host, printer.port, toEscPos(document, printer.paper_width));
}

async function round() {
  const { jobs } = await call('POST', 'jobs/claim', { limit: 10 });

  for (const job of jobs) {
    try {
      await print(job);
      await call('POST', `jobs/${job.id}/printed`);
      log(`printed ${job.type} on ${job.printer.name}`);
    } catch (error) {
      log(`could not print ${job.type} on ${job.printer.name}: ${error.message}`);
      await call('POST', `jobs/${job.id}/failed`, { error: error.message }).catch(() => {});
    }
  }
}

async function main() {
  if (!config.url || !config.key) {
    console.error('Set RASAHUB_URL and RASAHUB_GATEWAY_KEY. The key is made in the admin, under Branches.');
    process.exit(1);
  }

  const { business, branch } = await call('GET', 'ping');
  log(`connected to ${business} · ${branch}; printing to ${config.output === 'file' ? config.outputDir : 'printers'}`);

  for (;;) {
    try {
      await round();
    } catch (error) {
      log(`RasaHub could not be reached: ${error.message}`);
    }

    await new Promise((resolve) => setTimeout(resolve, config.pollSeconds * 1000));
  }
}

// Started directly (not imported by a test).
if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  main();
}
