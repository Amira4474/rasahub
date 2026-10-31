import { test } from 'node:test';
import assert from 'node:assert/strict';
import { asText, columnsFor, row, wrap } from '../src/layout.js';
import { toEscPos } from '../src/escpos.js';

test('80 mm paper fits 48 characters and 58 mm fits 32', () => {
  assert.equal(columnsFor(80), 48);
  assert.equal(columnsFor(58), 32);
});

test('long text wraps at spaces', () => {
  assert.deepEqual(wrap('Nasi Lemak Ayam Goreng Berempah', 12), ['Nasi Lemak', 'Ayam Goreng', 'Berempah']);
});

test('a row puts the amount at the right edge', () => {
  assert.deepEqual(row('Subtotal', '21.20', 20), ['Subtotal       21.20']);
  // A label too long for the line wraps, with the amount on its last line.
  assert.deepEqual(row('Teh Tarik Kurang Manis', '3.00', 16), ['Teh Tarik', 'Kurang', 'Manis       3.00']);
});

test('a document renders as text the width of the paper', () => {
  const text = asText({
    lines: [
      { type: 'text', text: 'BAR', align: 'center', size: 'normal', bold: true },
      { type: 'rule' },
      { type: 'row', left: 'TOTAL', right: '11.65', bold: true },
      { type: 'cut' },
    ],
  }, 58);

  const lines = text.split('\n');
  assert.equal(lines[0], ' '.repeat(14) + 'BAR');
  assert.equal(lines[1], '-'.repeat(32));
  assert.equal(lines[2], 'TOTAL' + ' '.repeat(22) + '11.65');
  assert.equal(lines[3].length, 32);
});

test('unknown kinds of line are skipped', () => {
  assert.equal(asText({ lines: [{ type: 'qr', data: 'x' }, { type: 'text', text: 'ok' }] }, 80), 'ok');
});

test('ESC/POS output starts by initialising and ends a cut', () => {
  const bytes = toEscPos({ lines: [{ type: 'text', text: 'Hi', size: 'large' }, { type: 'cut' }] }, 80);
  assert.deepEqual([...bytes.subarray(0, 2)], [0x1b, 0x40]);
  assert.ok(bytes.includes(Buffer.from([0x1d, 0x56, 0x42, 0x00])));
  assert.ok(bytes.includes(Buffer.from('Hi', 'latin1')));
});
